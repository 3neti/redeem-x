<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Backfill users.mobile from channels table
|
| These tests simulate the production scenario: users exist with mobile
| numbers ONLY in the channels table. The migration must copy them to
| users.mobile in E.164 format (+639173011987).
|--------------------------------------------------------------------------
*/

// ── Helpers ────────────────────────────────────────────────────────────

function createUserWithoutMobile(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'mobile' => null,
        'auth_source' => 'workos',
    ], $overrides));

    // Ensure mobile is truly NULL in the DB (bypass mutator/accessor)
    DB::table('users')->where('id', $user->id)->update(['mobile' => null]);

    return $user->fresh();
}

function insertChannelRow(int $userId, string $name, string $value): int
{
    return DB::table('channels')->insertGetId([
        'name' => $name,
        'value' => $value,
        'model_type' => 'App\\Models\\User',
        'model_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function runBackfillQuery(): void
{
    DB::table('channels')
        ->where('name', 'mobile')
        ->where('model_type', 'App\\Models\\User')
        ->orderByDesc('id')
        ->get()
        ->groupBy('model_id')
        ->each(function ($channels, $userId) {
            $latestValue = $channels->first()->value;

            try {
                $e164 = phone($latestValue, 'PH')->formatE164();
            } catch (\Throwable) {
                return;
            }

            DB::table('users')
                ->where('id', $userId)
                ->whereNull('mobile')
                ->update(['mobile' => $e164]);
        });
}

// ── Core: copies mobile from channels to users ─────────────────────────

it('copies E.164-without-plus from channels to users.mobile with plus', function () {
    $user = createUserWithoutMobile();
    insertChannelRow($user->id, 'mobile', '639173011987');

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBe('+639173011987');
});

it('copies national format from channels to users.mobile as E.164', function () {
    $user = createUserWithoutMobile();
    insertChannelRow($user->id, 'mobile', '09173011987');

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBe('+639173011987');
});

// ── Latest entry wins ──────────────────────────────────────────────────

it('uses the latest channel entry when multiple exist', function () {
    $user = createUserWithoutMobile();
    insertChannelRow($user->id, 'mobile', '639170000000');  // older
    insertChannelRow($user->id, 'mobile', '639173011987');  // newer (higher id)

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBe('+639173011987');
});

// ── Does not overwrite existing mobile ─────────────────────────────────

it('skips users who already have a mobile value', function () {
    $user = User::factory()->create([
        'mobile' => '+639991112222',
        'auth_source' => 'local',
    ]);
    insertChannelRow($user->id, 'mobile', '639173011987');

    runBackfillQuery();

    // Original value preserved — not overwritten
    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBe('+639991112222');
});

// ── Handles users without channel entries ──────────────────────────────

it('leaves mobile null for users without a channel entry', function () {
    $user = createUserWithoutMobile();
    // No channel row inserted

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBeNull();
});

// ── Skips unparseable values ───────────────────────────────────────────

it('skips channel entries with unparseable phone values', function () {
    $user = createUserWithoutMobile();
    insertChannelRow($user->id, 'mobile', 'not-a-phone');

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBeNull();
});

// ── Multiple users ─────────────────────────────────────────────────────

it('backfills multiple users correctly', function () {
    $userA = createUserWithoutMobile();
    $userB = createUserWithoutMobile();
    $userC = createUserWithoutMobile(); // no channel

    insertChannelRow($userA->id, 'mobile', '639173011987');
    insertChannelRow($userB->id, 'mobile', '639181234567');

    runBackfillQuery();

    expect(DB::table('users')->where('id', $userA->id)->value('mobile'))
        ->toBe('+639173011987');
    expect(DB::table('users')->where('id', $userB->id)->value('mobile'))
        ->toBe('+639181234567');
    expect(DB::table('users')->where('id', $userC->id)->value('mobile'))
        ->toBeNull();
});

// ── Ignores non-User model channels ────────────────────────────────────

it('ignores channels belonging to non-User models', function () {
    $user = createUserWithoutMobile();

    // Insert a channel for a different model type
    DB::table('channels')->insert([
        'name' => 'mobile',
        'value' => '639173011987',
        'model_type' => 'App\\Models\\Contact',
        'model_id' => $user->id,  // same numeric ID, different model
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBeNull();
});

// ── Ignores non-mobile channels ────────────────────────────────────────

it('ignores non-mobile channel entries', function () {
    $user = createUserWithoutMobile();
    insertChannelRow($user->id, 'webhook', 'https://example.com/hook');

    runBackfillQuery();

    expect(DB::table('users')->where('id', $user->id)->value('mobile'))
        ->toBeNull();
});
