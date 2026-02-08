<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;

class TestNetbankAccountCreation extends Command
{
    protected $signature = 'netbank:test-account-creation';

    protected $description = 'Smoke test for NetBank Account-As-A-Service API';

    public function handle()
    {
        $this->info('🚀 NetBank Account-As-A-Service Smoke Test');
        $this->info('==========================================');
        $this->newLine();

        // Step 1: Authenticate
        $this->info('Step 1: OAuth2 Authentication');
        try {
            $gateway = OmnipayFactory::create('netbank');
            $this->info('✓ Gateway initialized');
        } catch (\Exception $e) {
            $this->error('✗ Failed to initialize gateway: '.$e->getMessage());

            return 1;
        }

        // Step 2: Get Account Types - Try multiple endpoint variations
        $this->newLine();
        $this->info('Step 2: Fetch Available Account Types');

        // Try different endpoint URLs based on working endpoints
        $endpointVariations = [
            'https://api.netbank.ph/v1/account-types',  // Use production base like other working endpoints
            'https://api.netbank.ph/v1/account_types',  // Try underscore
            'https://api.netbank.ph/v1/accounts/types', // Try nested path
            'https://api-sandbox.netbank.ph/v1/account-types', // Original sandbox
        ];

        $accountTypeId = null;
        $accountTypesResponse = null;

        foreach ($endpointVariations as $index => $endpoint) {
            $this->line('  Trying endpoint '.($index + 1).': '.$endpoint);

            try {
                // Override the endpoint for this attempt
                $gateway->setAccountTypesEndpoint($endpoint);
                $accountTypesResponse = $gateway->getAccountTypes()->send();

                if ($accountTypesResponse->isSuccessful()) {
                    $types = $accountTypesResponse->getAccountTypes();
                    $this->info('  ✓ Success! Account types fetched: '.count($types).' types available');

                    foreach ($types as $type) {
                        $this->line('    - ID: '.($type['id'] ?? 'N/A').' | Name: '.($type['name'] ?? 'N/A'));
                    }

                    // Use first available account type
                    $accountTypeId = $types[0]['id'] ?? null;
                    if (! $accountTypeId) {
                        $this->error('  ✗ No account types available in response');

                        continue;
                    }
                    break; // Success! Exit loop
                } else {
                    $this->line('  ✗ Failed: '.$accountTypesResponse->getMessage());
                }
            } catch (\Exception $e) {
                $this->line('  ✗ Exception: '.$e->getMessage());
            }
        }

        if (! $accountTypeId) {
            $this->error('\n✗ Could not fetch account types from any endpoint');
            if ($accountTypesResponse) {
                $this->line('  Last response: '.json_encode($accountTypesResponse->getData(), JSON_PRETTY_PRINT));
            }
            $this->newLine();
            $this->warn('💡 Trying to proceed without account types - will use a default account type ID...');
            $this->line('  Using account_type_id = "8" (UAT Test Corporate Account from docs)');
            $accountTypeId = '8';
        }

        // Step 3: Create Customer
        $this->newLine();
        $this->info('Step 3: Create Customer Record');

        // Parse address
        $addressParts = explode(',', '8 West Maya Drive, Philam Homes, Quezon City 1104, Philippines');
        $line1 = trim($addressParts[0] ?? '8 West Maya Drive');
        $city = trim($addressParts[2] ?? 'Quezon City');
        $province = 'Metro Manila';
        $postalCode = '1104';

        // Parse birthdate (21-04-1970 => day: 21, month: 4, year: 1970)
        $birthdate = [
            'day' => 21,
            'month' => 4,
            'year' => 1970,
        ];

        // Parse phone number (+639173011987 => country_code: 63, number: 9173011987)
        $phone = [
            'country_code' => '63',
            'number' => '9173011987',
        ];

        $customerData = [
            'first_name' => 'Lester',
            'last_name' => 'Hurtado',
            'middle_name' => 'Biodora',
            'gender' => 'MALE',
            'birthdate' => $birthdate,
            'birth_place' => 'Manila',
            'birth_place_country' => 'PH',
            'email' => 'lester@hurtado.ph',
            'civil_status' => 'MARRIED',
            'tin' => '143-362-947',
            'customer_risk_level' => 'LOW',
            'address' => [
                'line1' => $line1,
                'line2' => 'Philam Homes',
                'city' => $city,
                'province' => $province,
                'postal_code' => $postalCode,
                'country' => 'PH',
            ],
            'primary_phone' => $phone,
        ];

        $this->line('  Customer Data:');
        $this->line('  '.json_encode($customerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Override the customer endpoint to use production URL
        $gateway->setCustomerEndpoint('https://api.netbank.ph/v1/customer');
        $this->line('  Using endpoint: '.$gateway->getCustomerEndpoint());

        try {
            // Create the request and initialize with customer data
            $request = $gateway->createCustomer(['customerData' => $customerData]);
            $customerResponse = $request->send();

            if ($customerResponse->isSuccessful()) {
                $customerId = $customerResponse->getCustomerId();
                $this->info('✓ Customer created: ID = '.$customerId);
            } else {
                $this->error('✗ Failed to create customer: '.$customerResponse->getMessage());
                $this->line('  Response code: '.$customerResponse->getCode());
                $this->line('  Response data: '.json_encode($customerResponse->getData(), JSON_PRETTY_PRINT));

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception: '.$e->getMessage());

            return 1;
        }

        // Step 4: Create Account
        $this->newLine();
        $this->info('Step 4: Create Bank Account');

        $accountData = [
            'customer_id' => $customerId,
            'account_type_id' => $accountTypeId,
            'description' => 'Redeem-X Test Account - Lester Hurtado',
        ];

        $this->line('  Account Data:');
        $this->line('  '.json_encode($accountData, JSON_PRETTY_PRINT));

        try {
            $accountResponse = $gateway->createAccount($accountData)->send();

            if ($accountResponse->isSuccessful()) {
                $accountNumber = $accountResponse->getAccountNumber();
                $this->info('✓ Account created: '.$accountNumber);
            } else {
                $this->error('✗ Failed to create account: '.$accountResponse->getMessage());
                $this->line('  Response code: '.$accountResponse->getCode());
                $this->line('  Response data: '.json_encode($accountResponse->getData(), JSON_PRETTY_PRINT));

                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception: '.$e->getMessage());

            return 1;
        }

        // Success Summary
        $this->newLine();
        $this->info('==========================================');
        $this->info('▶ Test PASSED');
        $this->info('==========================================');
        $this->line('  Customer ID: '.$customerId);
        $this->line('  Account Number: '.$accountNumber);
        $this->line('  Account Type ID: '.$accountTypeId);
        $this->newLine();
        $this->line('  ▶ View in NetBank Dashboard:');
        $this->line('    https://virtual.netbank.ph/dashboard');
        $this->newLine();

        return 0;
    }
}
