<?php

use Tests\Concerns\SetsUpRedemptionEnvironment;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendFeedbacksNotification;

uses(RefreshDatabase::class, SetsUpRedemptionEnvironment::class);

beforeEach(function () {
    // Set up shared redemption environment (seeding, mocking, etc.)
    $systemUser = $this->setUpRedemptionEnvironment();
    
    // Fake notifications for these tests
    Notification::fake();
    
    // Act as system user with funds
    $this->actingAs($systemUser);
});

dataset('voucher_instructions_with_feedback', function () {
    return [
        'with email and sms feedback' => [fn() => VoucherInstructionsData::from([
            'cash' => [
                'amount' => 2000,
                'currency' => 'USD',
                'validation' => [
                    'secret' => '123456',
                    'mobile' => '09179876543',
                    'country' => 'US',
                    'location' => 'New York',
                    'radius' => '5000m',
                ],
            ],
            'inputs' => [
                'fields' => [
                    VoucherInputField::EMAIL->value,
                    VoucherInputField::MOBILE->value,
                ],
            ],
            'feedback' => [
                'email' => 'support@company.com',
                'mobile' => '09179876543',
            ],
            'rider' => [
                'message' => 'Welcome!',
                'url' => 'https://company.com/welcome',
            ],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '******',
            'ttl' => 'PT24H',
        ])],
        'with all feedback channels' => [fn() => VoucherInstructionsData::from([
            'cash' => [
                'amount' => 5000,
                'currency' => 'PHP',
                'validation' => [],
            ],
            'inputs' => [
                'fields' => [
                    VoucherInputField::MOBILE->value,
                    VoucherInputField::LOCATION->value,
                ],
            ],
            'feedback' => [
                'email' => 'admin@test.com',
                'mobile' => '09181234567',
                'webhook' => 'https://test.com/webhook',
            ],
            'rider' => [],
            'count' => 1,
            'prefix' => 'FULL',
            'mask' => '####',
            'ttl' => 'P7D',
        ])],
    ];
});

it('sends notifications with correct content when voucher is redeemed', function (VoucherInstructionsData $instructions) {
    // Generate voucher
    $generateAction = app(GenerateVouchers::class);
    $vouchers = $generateAction->handle($instructions);
    $voucher = $vouchers->first();
    
    // Create contact and redeem
    $contact = Contact::factory()->create(['mobile' => '09171234567']);
    
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    // Create notification instance and test its content directly
    $notification = new SendFeedbacksNotification($voucher->code);
    
    // Test email content
    $mailData = $notification->toMail($instructions->feedback);
    expect($mailData->subject)->toBe('Voucher Code Redeemed')
        ->and($mailData->introLines[0])->toContain($voucher->code)
        ->and($mailData->introLines[1])->toContain($contact->mobile);
    
    // Test SMS content
    $smsData = $notification->toEngageSpark($instructions->feedback);
    $expectedAmount = "{$instructions->cash->currency} {$instructions->cash->amount}";
    expect($smsData->content)->toContain($voucher->code)
        ->and($smsData->content)->toContain($expectedAmount)
        ->and($smsData->content)->toContain($contact->mobile);
    
    // TODO: Test webhook payload once webhook channel is re-enabled
})->with('voucher_instructions_with_feedback');

it('includes location in notification when redeemer provides location', function () {
    // TODO: Fix Input model creation issues
    $this->markTestSkipped('Skipping until Input model creation is fixed');
    // Redeem with location data
    $locationData = [
        'address' => [
            'formatted' => '123 Main St, New York, NY 10001, USA',
        ],
        'coordinates' => [
            'lat' => 40.7128,
            'lng' => -74.0060,
        ],
    ];
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 1000, 'currency' => 'USD', 'validation' => []],
        'inputs' => ['fields' => [VoucherInputField::MOBILE->value, VoucherInputField::LOCATION->value]],
        'feedback' => ['email' => 'test@test.com', 'mobile' => '09171234567'],
        'rider' => [],
        'count' => 1,
        'prefix' => 'LOC',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    // Generate voucher
    $generateAction = app(GenerateVouchers::class);
    $vouchers = $generateAction->handle($instructions);
    $voucher = $vouchers->first();
    
    $contact = Contact::factory()->create(['mobile' => '09171234567']);
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    // Manually add location input to the contact (redeemer) for testing notification content
    $voucher->refresh();
    $redeemer = $voucher->redeemers->first();
    
    // Use the ModelInput package to add input directly
    \LBHurtado\ModelInput\Models\Input::create([
        'model_type' => get_class($redeemer),
        'model_id' => $redeemer->id,
        'name' => 'location',
        'value' => json_encode($locationData),
    ]);
    
    // Create notification instance and verify location is included
    $notification = new SendFeedbacksNotification($voucher->code);
    $feedback = ['email' => 'test@test.com', 'mobile' => '09171234567'];
    
    // Check email includes location
    $mailData = $notification->toMail($feedback);
    expect($mailData->introLines[1])->toContain($locationData['address']['formatted']);
    
    // Check SMS includes location
    $smsData = $notification->toEngageSpark($feedback);
    expect($smsData->content)->toContain($locationData['address']['formatted']);
    
    // Check webhook includes location
    $webhookData = $notification->toWebhook($feedback);
    expect($webhookData['payload']['redeemer']['address'])->toBe($locationData['address']['formatted']);
});

it('includes signature attachment in email when signature is provided', function () {
    // TODO: Fix Input model creation issues
    $this->markTestSkipped('Skipping until Input model creation is fixed');
    // Create a small base64 encoded PNG image (1x1 transparent pixel)
    $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 1500, 'currency' => 'USD', 'validation' => []],
        'inputs' => ['fields' => [VoucherInputField::MOBILE->value, VoucherInputField::SIGNATURE->value]],
        'feedback' => ['email' => 'test@test.com'],
        'rider' => [],
        'count' => 1,
        'prefix' => 'SIG',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    // Generate voucher
    $generateAction = app(GenerateVouchers::class);
    $vouchers = $generateAction->handle($instructions);
    $voucher = $vouchers->first();
    
    $contact = Contact::factory()->create(['mobile' => '09171234567']);
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    // Manually add signature input to the redeemer for testing notification content
    $voucher->refresh();
    $redeemer = $voucher->redeemers->first();
    
    // Use the ModelInput package to add input directly
    \LBHurtado\ModelInput\Models\Input::create([
        'model_type' => get_class($redeemer),
        'model_id' => $redeemer->id,
        'name' => 'signature',
        'value' => $signatureData,
    ]);
    
    // Create notification instance and check signature attachment
    $notification = new SendFeedbacksNotification($voucher->code);
    $mailData = $notification->toMail(['email' => 'test@test.com']);
    
    // Check that there's an attachment
    expect($mailData->attachments)->toHaveCount(1);
});
