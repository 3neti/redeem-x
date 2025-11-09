<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('authenticated user can list contacts', function () {
    Sanctum::actingAs($this->user);

    // Create some contacts
    Contact::factory()->count(5)->create();

    $response = $this->getJson('/api/v1/contacts');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'filters',
                'stats' => [
                    'total',
                    'withEmail',
                    'withName',
                ],
            ],
            'meta',
        ]);

    expect($response->json('data.pagination.total'))->toBeGreaterThanOrEqual(5);
});

test('contacts list can search', function () {
    Sanctum::actingAs($this->user);

    // Create contact with specific mobile
    Contact::factory()->create(['mobile' => '09171234567']);
    Contact::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/contacts?search=09171234567');

    $response->assertStatus(200);
    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.data.0.mobile'))->toBe('09171234567');
});

test('contacts list supports pagination', function () {
    Sanctum::actingAs($this->user);

    // Create 25 contacts
    Contact::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/contacts?per_page=10');

    $response->assertStatus(200);
    expect($response->json('data.pagination.per_page'))->toBe(10);
    expect($response->json('data.pagination.total'))->toBeGreaterThanOrEqual(25);
    expect($response->json('data.pagination.last_page'))->toBeGreaterThanOrEqual(3);
});

test('authenticated user can show contact', function () {
    Sanctum::actingAs($this->user);

    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'name' => 'John Doe',
    ]);

    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'contact' => [
                    'id',
                    'mobile',
                    'name',
                    'email',
                ],
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'contact' => [
                    'mobile' => '09171234567',
                    'name' => 'John Doe',
                ],
            ],
        ]);
});

test('unauthenticated user cannot access contacts api', function () {
    $response = $this->getJson('/api/v1/contacts');
    $response->assertStatus(401);
});
