<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->deposit(100000);

    // Create real token for authentication
    $token = $this->user->createToken('test-token');
    $this->withToken($token->plainTextToken);
});

describe('List Deposits API', function () {
    it('returns paginated deposits', function () {
        // Create deposits for this user
        for ($i = 0; $i < 3; $i++) {
            $sender = Contact::factory()->create();
            $this->user->recordDepositFrom($sender, 1000 + ($i * 100), [
                'reference' => 'REF-'.$i,
            ]);
        }

        $response = $this->getJson('/api/v1/deposits');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'deposits' => [
                        '*' => [
                            'id',
                            'amount',
                            'sender',
                        ],
                    ],
                    'pagination',
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('returns empty deposits when none exist', function () {
        $response = $this->getJson('/api/v1/deposits');

        $response
            ->assertOk()
            ->assertJsonPath('data.deposits', []);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/deposits');

        $response->assertUnauthorized();
    });
});

describe('Deposit Statistics API', function () {
    it('returns deposit statistics', function () {
        // Create some deposits
        for ($i = 0; $i < 3; $i++) {
            $sender = Contact::factory()->create();
            $this->user->recordDepositFrom($sender, 1000, ['reference' => 'REF-'.$i]);
        }

        $response = $this->getJson('/api/v1/deposits/stats');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_amount',
                        'total_count',
                        'currency',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('returns zero stats when no deposits exist', function () {
        $response = $this->getJson('/api/v1/deposits/stats');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.total_count', 0);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/deposits/stats');

        $response->assertUnauthorized();
    });
});

describe('List Senders API', function () {
    it('returns paginated senders', function () {
        // Create senders with deposits
        for ($i = 0; $i < 3; $i++) {
            $sender = Contact::factory()->create();
            $this->user->recordDepositFrom($sender, 1000, ['reference' => 'REF-'.$i]);
        }

        $response = $this->getJson('/api/v1/senders');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'senders' => [
                        '*' => [
                            'id',
                            'mobile',
                            'name',
                        ],
                    ],
                    'pagination',
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('returns empty senders when none exist', function () {
        $response = $this->getJson('/api/v1/senders');

        $response
            ->assertOk()
            ->assertJsonPath('data.senders', []);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/senders');

        $response->assertUnauthorized();
    });
});

describe('Show Sender API', function () {
    it('returns sender details with transaction history', function () {
        $sender = Contact::factory()->create(['name' => 'John Sender']);
        $this->user->recordDepositFrom($sender, 2000, ['reference' => 'REF-001']);

        $response = $this->getJson("/api/v1/senders/{$sender->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'sender' => [
                        'id',
                        'mobile',
                        'name',
                    ],
                    'stats',
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.sender.name', 'John Sender');
    });

    it('returns 404 for non-existent sender', function () {
        $response = $this->getJson('/api/v1/senders/99999');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/senders/1');

        $response->assertUnauthorized();
    });
});
