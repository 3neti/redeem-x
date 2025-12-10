<?php

use LBHurtado\PaymentGateway\Support\BankRegistry;
use Illuminate\Support\Collection;

//beforeEach(function () {
//    // Define the mock file path using the helper
//    $this->banksPath = documents_path('banks.json');
//
//    // Write temporary banks.json file with the provided data
//    file_put_contents($this->banksPath, json_encode([
//        'banks' => [
//            "AGBUPHM1XXX" => [
//                "full_name" => "AGRIBUSINESS RURAL BANK, INC.",
//                "swift_bic" => "AGBUPHM1XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "AGBUPHM1XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//            "AIIPPHM1XXX" => [
//                "full_name" => "AL-AMANAH ISLAMIC BANK",
//                "swift_bic" => "AIIPPHM1XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "AIIPPHM1XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//            "ALKBPHM2XXX" => [
//                "full_name" => "ALLBANK, INC.",
//                "swift_bic" => "ALKBPHM2XXX",
//                "settlement_rail" => [
//                    "PESONET" => [
//                        "bank_code" => "ALKBPHM2XXX",
//                        "name" => "PESONET"
//                    ]
//                ]
//            ],
//        ],
//    ]));
//});

//afterEach(function () {
//    // Remove the mock banks.json file using the helper path
//    if (file_exists($this->banksPath)) {
//        unlink($this->banksPath);
//    }
//});

it('validates that the banks.json file exists and the BankRegistry loads it correctly', function () {
    // Path to the banks.json file using the helper
    $path = documents_path('banks.json');

    // Check that the file exists
    expect(file_exists($path))->toBeTrue();

    // Instantiate the BankRegistry (should not throw an exception)
    $bankRegistry = new BankRegistry();

    // Validate all() returns data
    $allBanks = $bankRegistry->all();

    expect($allBanks)->toBeArray()->not->toBeEmpty();
});

//it('throws an exception if the file format is invalid', function () {
//    // Overwrite banks.json with invalid data
//    file_put_contents($this->banksPath, json_encode([]));
//
//    $this->expectException(UnexpectedValueException::class);
//    $this->expectExceptionMessage("Invalid format in banks.json. Expected 'banks' root key.");
//
//    (new BankRegistry());
//});

it('returns all banks using the all() method', function () {
    $bankRegistry = new BankRegistry();

    $allBanks = $bankRegistry->all();

    expect($allBanks)
        ->toBeArray()
        ->toHaveCount(146)
        ->and($allBanks['AGBUPHM1XXX']['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.')
        ->and($allBanks['AIIPPHM1XXX']['full_name'])
        ->toBe('AL-AMANAH ISLAMIC BANK')
        ->and($allBanks['ALKBPHM2XXX']['full_name'])
        ->toBe('ALLBANK, INC.');
});

it('finds a bank by swift_bic using the find() method', function () {
    $bankRegistry = new BankRegistry();

    // Test existing bank
    $bank = $bankRegistry->find('AGBUPHM1XXX');
    expect($bank)
        ->toBeArray()
        ->and($bank['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.');

    // Test nonexistent bank
    expect($bankRegistry->find('NOT_EXISTENT'))->toBeNull();
});

it('returns supported settlement rails for a bank', function () {
    $bankRegistry = new BankRegistry();

    // Test settlement rail for an existing bank
    $rails = $bankRegistry->supportedSettlementRails('AGBUPHM1XXX');
    expect($rails)
        ->toBeArray()
        ->and($rails['PESONET']['bank_code'])
        ->toBe('AGBUPHM1XXX')
        ->and($rails['PESONET']['name'])
        ->toBe('PESONET');

    // Test settlement rails for a nonexistent bank
    $rails = $bankRegistry->supportedSettlementRails('NOT_EXISTENT');
    expect($rails)->toBeArray()->toBeEmpty();
});

it('returns a collection using the toCollection() method', function () {
    $bankRegistry = new BankRegistry();

    $collection = $bankRegistry->toCollection();

    expect($collection)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(146)
        ->and($collection->get('AGBUPHM1XXX')['full_name'])
        ->toBe('AGRIBUSINESS RURAL BANK, INC.');
});

it('checks if bank supports a specific rail using supportsRail()', function () {
    $bankRegistry = new BankRegistry();

    // Bank that supports PESONET
    $result = $bankRegistry->supportsRail('AGBUPHM1XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET);
    expect($result)->toBeTrue();

    // Bank doesn't support INSTAPAY (assuming AGBUPHM1XXX only has PESONET)
    $result = $bankRegistry->supportsRail('AGBUPHM1XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::INSTAPAY);
    expect($result)->toBeFalse();

    // Non-existent bank
    $result = $bankRegistry->supportsRail('NONEXISTENT', \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET);
    expect($result)->toBeFalse();
});

it('filters banks by rail using byRail()', function () {
    $bankRegistry = new BankRegistry();

    // Get all banks supporting PESONET
    $banks = $bankRegistry->byRail(\LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET);

    expect($banks)->toBeInstanceOf(Collection::class);
    expect($banks->count())->toBeGreaterThan(0);

    // Each bank should have PESONET in settlement_rail
    foreach ($banks as $bank) {
        expect($bank)->toHaveKey('settlement_rail');
        expect($bank['settlement_rail'])->toHaveKey('PESONET');
    }
});

it('gets all EMIs using getEMIs()', function () {
    $bankRegistry = new BankRegistry();

    $emis = $bankRegistry->getEMIs();

    expect($emis)->toBeInstanceOf(Collection::class);

    // Check that each code matches an EMI pattern
    $emiPatterns = ['GXCH', 'PAPH', 'DCPH', 'GHPE', 'SHPH', 'TAGC'];

    foreach ($emis->keys() as $code) {
        $matchesPattern = false;
        foreach ($emiPatterns as $pattern) {
            if (str_starts_with($code, $pattern)) {
                $matchesPattern = true;
                break;
            }
        }
        expect($matchesPattern)->toBeTrue();
    }
});

it('checks if code is EMI using isEMI()', function () {
    $bankRegistry = new BankRegistry();

    // Assuming GXCHPHM2XXX is GCash (EMI) if it exists in banks.json
    // This test depends on your actual data
    $isEmi = $bankRegistry->isEMI('GXCHPHM2XXX');
    expect($isEmi)->toBeBool();

    // Traditional bank (not EMI)
    $isEmi = $bankRegistry->isEMI('AGBUPHM1XXX');
    expect($isEmi)->toBeFalse();
});

// EMI Rail Restriction Tests

it('restricts GCash to INSTAPAY only (EMI override)', function () {
    $bankRegistry = new BankRegistry();

    $allowedRails = $bankRegistry->getAllowedRails('GXCHPHM2XXX');

    // GCash should only support INSTAPAY (from bank-restrictions config)
    expect($allowedRails)->toBe(['INSTAPAY']);

    // Verify supportsRail() respects the restriction
    expect($bankRegistry->supportsRail('GXCHPHM2XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::INSTAPAY))->toBeTrue();
    expect($bankRegistry->supportsRail('GXCHPHM2XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET))->toBeFalse();
});

it('restricts PayMaya to INSTAPAY only (EMI override)', function () {
    $bankRegistry = new BankRegistry();

    $allowedRails = $bankRegistry->getAllowedRails('PYMYPHM2XXX');

    // PayMaya should only support INSTAPAY
    expect($allowedRails)->toBe(['INSTAPAY']);
    expect($bankRegistry->supportsRail('PYMYPHM2XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::INSTAPAY))->toBeTrue();
    expect($bankRegistry->supportsRail('PYMYPHM2XXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET))->toBeFalse();
});

it('allows traditional banks to use both rails (no override)', function () {
    $bankRegistry = new BankRegistry();

    // BDO supports both rails (no EMI restriction)
    $allowedRails = $bankRegistry->getAllowedRails('BNORPHMMXXX');
    expect($allowedRails)->toContain('INSTAPAY');
    expect($allowedRails)->toContain('PESONET');

    expect($bankRegistry->supportsRail('BNORPHMMXXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::INSTAPAY))->toBeTrue();
    expect($bankRegistry->supportsRail('BNORPHMMXXX', \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET))->toBeTrue();
});

it('maintains backward compatibility with getAllowedRails()', function () {
    $bankRegistry = new BankRegistry();

    // Test bank with only PESONET (no override, fallback to banks.json)
    $allowedRails = $bankRegistry->getAllowedRails('AGBUPHM1XXX');
    expect($allowedRails)->toBe(['PESONET']);

    // Test bank with both rails (no override)
    $allowedRails = $bankRegistry->getAllowedRails('BOPIPHMMXXX'); // BPI
    expect($allowedRails)->toContain('INSTAPAY');
    expect($allowedRails)->toContain('PESONET');
});

it('restricts all configured EMIs to INSTAPAY only', function () {
    $bankRegistry = new BankRegistry();
    $restrictions = config('bank-restrictions.emi_restrictions');

    foreach ($restrictions as $swiftBic => $config) {
        $allowedRails = $bankRegistry->getAllowedRails($swiftBic);

        expect($allowedRails)
            ->toBe(['INSTAPAY'], "$swiftBic should only support INSTAPAY");

        expect($bankRegistry->supportsRail($swiftBic, \LBHurtado\PaymentGateway\Enums\SettlementRail::PESONET))
            ->toBeFalse("$swiftBic should not support PESONET");
    }
});
