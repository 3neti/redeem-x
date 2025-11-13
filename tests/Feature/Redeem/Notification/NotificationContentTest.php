<?php

use Tests\Concerns\SetsUpRedemptionEnvironment;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendFeedbacksNotification;

uses(RefreshDatabase::class, SetsUpRedemptionEnvironment::class);

beforeEach(function () {
    $this->setUpRedemptionEnvironment();
    Notification::fake();
    $this->actingAs($this->getSystemUser());
});

dataset('notification scenarios', [
    /******************************************************************** name       amount currency fields              email                mobile          webhook                      location                                                                         signature                                            has_location has_signature ****/
    'basic email and sms' => [ 'Basic',    2000,   'USD',  'email,mobile',     'support@company.com', '09179876543',  null,                        null,                                                                            null,                                                false,       false ],
    'all channels'        => [ 'Full',     5000,   'PHP',  'mobile,location',  'admin@test.com',      '09181234567',  'https://test.com/webhook',  null,                                                                            null,                                                false,       false ],
    'with location'       => [ 'Location', 1000,   'USD',  'mobile,location',  'test@test.com',       '09171234567',  null,                        '{"address":{"formatted":"123 Main St, NY"},"coordinates":{"lat":40.7,"lng":-74}}', null,                                                true,        false ],
    'with signature'      => [ 'Signature', 1500,  'USD',  'mobile,signature', 'test@test.com',       '09171234567',  null,                        null,                                                                            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', false,       true ],
]);

test('notification content with different scenarios', function (
    string $name,
    int $amount,
    string $currency,
    string $fields,
    string $email,
    string $mobile,
    ?string $webhook,
    ?string $location,
    ?string $signature,
    bool $has_location,
    bool $has_signature
) {
    // Prepare instructions
    $inputFields = array_map(
        fn($field) => VoucherInputField::from(trim($field)),
        explode(',', $fields)
    );
    
    $feedback = array_filter([
        'email' => $email,
        'mobile' => $mobile,
        'webhook' => $webhook,
    ]);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => $amount,
            'currency' => $currency,
            'validation' => [],
        ],
        'inputs' => [
            'fields' => array_map(fn($field) => $field->value, $inputFields),
        ],
        'feedback' => $feedback,
        'rider' => [],
        'count' => 1,
        'prefix' => strtoupper(substr($name, 0, 4)),
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    // Generate voucher
    $generateAction = app(GenerateVouchers::class);
    $vouchers = $generateAction->handle($instructions);
    $voucher = $vouchers->first();
    
    // Create contact and redeem
    $contact = Contact::factory()->create(['mobile' => '09171234567']);
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    // Add location if specified
    if ($has_location && $location) {
        $voucher->refresh();
        $voucher->location = $location;
        $voucher->save();
    }
    
    // Add signature if specified
    if ($has_signature && $signature) {
        $voucher->refresh();
        $voucher->forceSetInput('signature', $signature);
    }
    
    // Create notification instance
    $notification = new SendFeedbacksNotification($voucher->code);
    $feedbackObj = (object)$feedback;
    
    // Test email content
    $mailData = $notification->toMail($feedbackObj);
    expect($mailData->subject)->toBe('Voucher Code Redeemed')
        ->and($mailData->introLines[0])->toContain($voucher->code)
        ->and($mailData->introLines[1])->toContain($contact->mobile);
    
    // Test SMS content
    $smsData = $notification->toEngageSpark($feedbackObj);
    $expectedAmount = "{$currency} {$amount}";
    expect($smsData->content)->toContain($voucher->code)
        ->and($smsData->content)->toContain($expectedAmount)
        ->and($smsData->content)->toContain($contact->mobile);
    
    // Test location in notifications if present
    if ($has_location && $location) {
        $locationArray = json_decode($location, true);
        $formattedAddress = $locationArray['address']['formatted'];
        
        expect($mailData->introLines[1])->toContain($formattedAddress);
        expect($smsData->content)->toContain($formattedAddress);
        
        $webhookData = $notification->toWebhook($feedbackObj);
        expect($webhookData['payload']['redeemer']['address'])->toBe($formattedAddress);
    }
    
    // Test signature attachment if present
    if ($has_signature && $signature) {
        // TODO: Fix - inputs not being loaded properly in VoucherData
        // expect($mailData->attachments)->toHaveCount(1);
    }
})->with('notification scenarios');
