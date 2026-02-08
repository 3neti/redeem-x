<?php

/**
 * Integration tests for notification content and template rendering.
 *
 * These tests focus on:
 * - Full redemption → notification flow
 * - Template content accuracy with various data scenarios
 * - Content validation across all channels (email, SMS, webhook)
 * - Edge cases with different input combinations (location, signature, etc.)
 * - NOT class structure/methods (see SendFeedbacksNotificationTest)
 */

use App\Notifications\SendFeedbacksNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Tests\Concerns\SetsUpRedemptionEnvironment;

uses(RefreshDatabase::class, SetsUpRedemptionEnvironment::class);

beforeEach(function () {
    $this->setUpRedemptionEnvironment();
    Notification::fake();
    $this->actingAs($this->getSystemUser());
});

dataset('notification scenarios', [
    /**************************************************************************** name       amount currency fields                     email                mobile          webhook                      location                                                                         signature                                                                                                  selfie                                                                                               has_location has_signature has_selfie ****/
    'basic email and sms' => ['Basic',    2000,   'USD',  'email,mobile',            'support@company.com', '09179876543',  null,                        null,                                                                            null,                                                                                                  null,                                                                                                false,       false,        false],
    'all channels' => ['Full',     5000,   'PHP',  'mobile,location',         'admin@test.com',      '09181234567',  'https://test.com/webhook',  null,                                                                            null,                                                                                                  null,                                                                                                false,       false,        false],
    'with location' => ['Location', 1000,   'USD',  'mobile,location',         'test@test.com',       '09171234567',  null,                        '{"address":{"formatted":"123 Main St, NY"},"coordinates":{"lat":40.7,"lng":-74}}', null,                                                                                                  null,                                                                                                true,        false,        false],
    'with signature' => ['Signature', 1500,   'USD',  'mobile,signature',        'test@test.com',       '09171234567',  null,                        null,                                                                            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==', null,                                                                                                false,       true,         false],
    'with signature and selfie' => ['Images', 1000,   'PHP',  'mobile,signature,selfie', 'test@test.com',       '09171234567',  null,                        null,                                                                            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', false,       true,         true],
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
    ?string $selfie,
    bool $has_location,
    bool $has_signature,
    bool $has_selfie
) {
    // Prepare instructions
    $inputFields = array_map(
        fn ($field) => VoucherInputField::from(trim($field)),
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
            'fields' => array_map(fn ($field) => $field->value, $inputFields),
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
        // Add snapshot to location data for testing
        $locationData = json_decode($location, true);
        $locationData['snapshot'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYGD4DwABBAEAW9JJQQAAAABJRU5ErkJggg==';
        $voucher->location = json_encode($locationData);
        $voucher->save();
    }

    // Add signature if specified
    if ($has_signature && $signature) {
        $voucher->refresh();
        $voucher->forceSetInput('signature', $signature);
    }

    // Add selfie if specified
    if ($has_selfie && $selfie) {
        $voucher->refresh();
        $voucher->forceSetInput('selfie', $selfie);
    }

    // Create notification instance
    $notification = new SendFeedbacksNotification($voucher->code);
    $feedbackObj = (object) $feedback;

    // Test email content
    $mailData = $notification->toMail($feedbackObj);
    expect($mailData->subject)->toBe('Voucher Code Redeemed')
        ->and($mailData->introLines[0])->toContain($voucher->code)
        ->and($mailData->introLines[0])->toContain($contact->mobile);

    // Test SMS content
    $smsData = $notification->toEngageSpark($feedbackObj);
    expect($smsData->content)->toContain($voucher->code)
        ->and($smsData->content)->toContain($contact->mobile);

    // Test location in notifications if present
    if ($has_location && $location) {
        $locationArray = json_decode($location, true);
        $formattedAddress = $locationArray['address']['formatted'];

        expect($mailData->introLines[0])->toContain($formattedAddress);
        expect($smsData->content)->toContain($formattedAddress);

        $webhookData = $notification->toWebhook($feedbackObj);
        expect($webhookData['payload']['redeemer']['address'])->toBe($formattedAddress);
    }

    // Test signature, selfie, and location snapshot attachments if present
    $expectedAttachments = ($has_signature ? 1 : 0) + ($has_selfie ? 1 : 0) + ($has_location ? 1 : 0);
    if ($expectedAttachments > 0) {
        expect($mailData->rawAttachments)->toHaveCount($expectedAttachments);

        if ($has_signature) {
            $signatureAttachment = collect($mailData->rawAttachments)
                ->firstWhere('name', 'signature.png');
            expect($signatureAttachment)->not->toBeNull()
                ->and($signatureAttachment)->toHaveKey('data')
                ->and($signatureAttachment['options']['mime'])->toBe('image/png');
        }

        if ($has_selfie) {
            $selfieAttachment = collect($mailData->rawAttachments)
                ->firstWhere('name', 'selfie.png');
            expect($selfieAttachment)->not->toBeNull()
                ->and($selfieAttachment)->toHaveKey('data')
                ->and($selfieAttachment['options']['mime'])->toBe('image/png');
        }

        if ($has_location) {
            $locationAttachment = collect($mailData->rawAttachments)
                ->firstWhere('name', 'location-map.png');
            expect($locationAttachment)->not->toBeNull()
                ->and($locationAttachment)->toHaveKey('data')
                ->and($locationAttachment['options']['mime'])->toBe('image/png');
        }
    }
})->with('notification scenarios');
