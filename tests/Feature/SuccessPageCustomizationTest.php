<?php

use App\Models\User;
use App\Services\SuccessContentService;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Enums\VoucherInputField;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);
});

test('default plain text message renders correctly', function () {
    $service = new SuccessContentService();
    
    $context = [
        'voucher_code' => 'TEST123',
        'amount' => '₱500.00',
    ];
    
    $result = $service->processContent('Thank you for redeeming!', $context);
    
    expect($result)
        ->toBeArray()
        ->and($result['type'])->toBe('text')
        ->and($result['content'])->toBe('Thank you for redeeming!')
        ->and($result['raw'])->toBe('Thank you for redeeming!');
});

test('HTML content is detected and processed', function () {
    $service = new SuccessContentService();
    
    $context = ['voucher_code' => 'ABC123'];
    $htmlContent = '<h1>Success!</h1><p>Voucher redeemed</p>';
    
    $result = $service->processContent($htmlContent, $context);
    
    expect($result['type'])->toBe('html')
        ->and($result['content'])->toContain('<h1>Success!</h1>');
});

test('Markdown content is detected', function () {
    $service = new SuccessContentService();
    
    $context = ['voucher_code' => 'ABC123'];
    $markdownContent = '# Success!\n\n## Your voucher has been redeemed';
    
    $result = $service->processContent($markdownContent, $context);
    
    expect($result['type'])->toBe('markdown')
        ->and($result['content'])->toContain('# Success!');
});

test('SVG content is detected', function () {
    $service = new SuccessContentService();
    
    $context = ['voucher_code' => 'ABC123'];
    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40"/></svg>';
    
    $result = $service->processContent($svgContent, $context);
    
    expect($result['type'])->toBe('svg')
        ->and($result['content'])->toContain('<svg');
});

test('URL content is detected', function () {
    $service = new SuccessContentService();
    
    $context = ['voucher_code' => 'ABC123'];
    $urlContent = 'https://example.com/success';
    
    $result = $service->processContent($urlContent, $context);
    
    expect($result['type'])->toBe('url')
        ->and($result['content'])->toBe('https://example.com/success');
});

test('template variables are replaced', function () {
    config(['app.name' => 'Test App']);
    
    $service = new SuccessContentService();
    
    $context = [
        'voucher_code' => 'TEST123',
        'amount' => '₱1,000.00',
        'mobile' => '+639171234567',
    ];
    
    $content = 'Thank you for using {app_name}! Voucher {voucher_code} for {amount} has been sent to {mobile}.';
    $result = $service->processContent($content, $context);
    
    expect($result['content'])
        ->toContain('Test App')
        ->toContain('TEST123')
        ->toContain('₱1,000.00')
        ->toContain('+639171234567');
});

test('success page shows processed content when custom message exists', function () {
    $voucher = Vouchers::withOwner($this->user)
        ->withPrefix('CUSTOM')
        ->withMask('***')
        ->withMetadata([
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                    'validation' => CashValidationRulesData::from([
                        'secret' => null,
                        'mobile' => null,
                        'country' => null,
                        'location' => null,
                        'radius' => null,
                    ]),
                ]),
                'inputs' => InputFieldsData::from([
                    'fields' => [VoucherInputField::NAME->value],
                ]),
                'feedback' => FeedbackInstructionData::from([
                    'email' => null,
                    'mobile' => null,
                    'webhook' => null,
                ]),
                'rider' => RiderInstructionData::from([
                    'message' => '<h1>Thank you!</h1><p>Voucher {voucher_code} redeemed!</p>',
                    'url' => 'https://example.com',
                    'redirect_timeout' => 10,
                ]),
            ])->toArray(),
        ])
        ->create();
    
    // Mark as redeemed
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
    RedeemVoucher::run($contact, $voucher->code);
    
    $response = $this->get(route('disburse.success', ['voucher' => $voucher->code]));
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('disburse/Success')
            ->has('rider.processed_content')
            ->where('rider.processed_content.type', 'html')
            ->where('config.button_labels.continue', 'Continue Now')
        );
});

test('success page shows default message when no custom message', function () {
    // Set a default content in config
    config(['success.default_content' => '<h1>Default Success!</h1><p>Voucher redeemed</p>']);
    
    $voucher = Vouchers::withOwner($this->user)
        ->withPrefix('DEFAULT')
        ->withMask('***')
        ->withMetadata([
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                    'validation' => CashValidationRulesData::from([
                        'secret' => null,
                        'mobile' => null,
                        'country' => null,
                        'location' => null,
                        'radius' => null,
                    ]),
                ]),
                'inputs' => InputFieldsData::from([
                    'fields' => [VoucherInputField::NAME->value],
                ]),
                'feedback' => FeedbackInstructionData::from([
                    'email' => null,
                    'mobile' => null,
                    'webhook' => null,
                ]),
                'rider' => RiderInstructionData::from([
                    'message' => null,
                    'url' => null,
                ]),
            ])->toArray(),
        ])
        ->create();
    
    // Mark as redeemed
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
    RedeemVoucher::run($contact, $voucher->code);
    
    $response = $this->get(route('disburse.success', ['voucher' => $voucher->code]));
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('disburse/Success')
            ->where('rider.message', '<h1>Default Success!</h1><p>Voucher redeemed</p>')
            ->has('rider.processed_content')
            ->where('rider.processed_content.type', 'html')
        );
});

test('custom button labels are applied', function () {
    config([
        'success.button_label' => 'Continue to Partner',
        'success.dashboard_button_label' => 'Go Home',
        'success.redeem_another_label' => 'Redeem More',
    ]);
    
    $voucher = Vouchers::withOwner($this->user)
        ->withMetadata([
            'instructions' => VoucherInstructionsData::from([
                'cash' => CashInstructionData::from([
                    'amount' => 500,
                    'currency' => 'PHP',
                    'validation' => CashValidationRulesData::from([
                        'secret' => null,
                        'mobile' => null,
                        'country' => null,
                        'location' => null,
                        'radius' => null,
                    ]),
                ]),
                'inputs' => InputFieldsData::from(['fields' => [VoucherInputField::NAME->value]]),
                'feedback' => FeedbackInstructionData::from([
                    'email' => null,
                    'mobile' => null,
                    'webhook' => null,
                ]),
                'rider' => RiderInstructionData::from([]),
            ])->toArray(),
        ])
        ->create();
    
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
    RedeemVoucher::run($contact, $voucher->code);
    
    $response = $this->get(route('disburse.success', ['voucher' => $voucher->code]));
    
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('config.button_labels.continue', 'Continue to Partner')
            ->where('config.button_labels.dashboard', 'Go Home')
            ->where('config.button_labels.redeem_another', 'Redeem More')
        );
});

test('dynamic image URLs work in HTML content', function () {
    $service = new SuccessContentService();
    
    $context = ['voucher_code' => 'IMG123'];
    $htmlContent = '<div class="text-center"><img src="https://cataas.com/cat" alt="Success"/><p>Voucher {voucher_code} redeemed!</p></div>';
    
    $result = $service->processContent($htmlContent, $context);
    
    expect($result['type'])->toBe('html')
        ->and($result['content'])->toContain('https://cataas.com/cat')
        ->and($result['content'])->toContain('IMG123');
});

test('empty content returns null', function () {
    $service = new SuccessContentService();
    
    $result = $service->processContent(null, []);
    
    expect($result)->toBeNull();
    
    $result = $service->processContent('', []);
    
    expect($result)->toBeNull();
});
