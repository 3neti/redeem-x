<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->deposit(100000);

    // Create real token for authentication
    $token = $this->user->createToken('test-token');
    $this->withToken($token->plainTextToken);
});

describe('List Contacts API', function () {
    it('returns paginated contacts', function () {
        // Create contacts
        Contact::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/contacts');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'mobile',
                            'name',
                            'email',
                            'country',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                    'stats' => [
                        'total',
                        'withEmail',
                        'withName',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.pagination.total', 5);
    });

    it('searches contacts by name', function () {
        Contact::factory()->create(['name' => 'John Doe']);
        Contact::factory()->create(['name' => 'Jane Smith']);

        $response = $this->getJson('/api/v1/contacts?search=John');

        $response
            ->assertOk();
        expect($response->json('data.pagination.total'))->toBeGreaterThanOrEqual(1);
    });

    it('searches contacts by mobile', function () {
        Contact::factory()->create(['mobile' => '09171234567']);
        Contact::factory()->create(['mobile' => '09187654321']);

        $response = $this->getJson('/api/v1/contacts?search=09171234567');

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    });

    it('searches contacts by email', function () {
        Contact::factory()->create(['email' => 'john@example.com']);
        Contact::factory()->create(['email' => 'jane@example.com']);

        $response = $this->getJson('/api/v1/contacts?search=john');

        $response
            ->assertOk();
        expect($response->json('data.pagination.total'))->toBeGreaterThanOrEqual(1);
    });

    it('validates pagination parameters', function () {
        $response = $this->getJson('/api/v1/contacts?per_page=200');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('returns empty results when no contacts exist', function () {
        $response = $this->getJson('/api/v1/contacts');

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.stats.total', 0);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/contacts');

        $response->assertUnauthorized();
    });
});

describe('Show Contact API', function () {
    it('returns contact details', function () {
        $contact = Contact::factory()->create([
            'name' => 'John Doe',
            'mobile' => '09171234567',
        ]);

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'contact' => [
                        'id',
                        'mobile',
                        'name',
                        'email',
                        'country',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.contact.mobile', '09171234567')
            ->assertJsonPath('data.contact.name', 'John Doe');
    });

    it('returns 404 for non-existent contact', function () {
        $response = $this->getJson('/api/v1/contacts/99999');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertUnauthorized();
    });
});

describe('Get Contact Vouchers API', function () {
    it('returns contact vouchers', function () {
        $contact = Contact::factory()->create();
        
        // Create vouchers for this contact
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
        foreach ($vouchers as $voucher) {
            // Update voucher to mark as redeemed
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson("/api/v1/contacts/{$contact->id}/vouchers");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'vouchers',
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('returns empty vouchers for contact with no redemptions', function () {
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}/vouchers");

        $response
            ->assertOk()
            ->assertJsonPath('data.vouchers', []);
    });

    it('returns 404 for non-existent contact', function () {
        $response = $this->getJson('/api/v1/contacts/99999/vouchers');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}/vouchers");

        $response->assertUnauthorized();
    });
});
