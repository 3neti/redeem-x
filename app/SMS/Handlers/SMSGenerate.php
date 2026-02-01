<?php

namespace App\SMS\Handlers;

use LBHurtado\Voucher\Actions\GenerateVouchers as BaseGenerateVouchers;
use App\Models\Campaign;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * SMS handler for generating REDEEMABLE vouchers.
 * 
 * Supports commands:
 * - GENERATE {amount} [--flags]
 * - REDEEMABLE {amount} [--flags]
 */
class SMSGenerate extends BaseSMSVoucherHandler
{
    protected function getInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED),
            new InputArgument('amount', InputArgument::REQUIRED),
            new InputOption('count', null, InputOption::VALUE_REQUIRED, 'Number of vouchers', 1),
            new InputOption('campaign', null, InputOption::VALUE_REQUIRED, 'Campaign name'),
            new InputOption('rider-message', null, InputOption::VALUE_REQUIRED, 'Rider message'),
            new InputOption('prefix', null, InputOption::VALUE_REQUIRED, 'Voucher code prefix'),
            new InputOption('mask', null, InputOption::VALUE_REQUIRED, 'Voucher code mask'),
            new InputOption('ttl', null, InputOption::VALUE_REQUIRED, 'TTL in days'),
            new InputOption('settlement-rail', null, InputOption::VALUE_REQUIRED, 'Settlement rail (INSTAPAY/PESONET)'),
            new InputOption('inputs', null, InputOption::VALUE_REQUIRED, 'Input fields (comma-separated)'),
        ]);
    }

    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        // User already authenticated by middleware
        $user = request()->user();
        
        try {
            // Parse full message for flags using Symfony Console
            $parsed = $this->parseCommand(
                $values['_message'] ?? '',
                $this->getInputDefinition()
            );
            
            // Router also extracted {amount}, but parseCommand has it too
            $amount = (float) ($parsed['arguments']['amount'] ?? $values['amount'] ?? 0);
            $options = $parsed['options'];
            
            // Validate amount
            if ($amount <= 0) {
                return response()->json([
                    'message' => '❌ Invalid amount. Amount must be greater than 0.'
                ]);
            }
            
            // Handle --campaign option
            $campaign = null;
            if (!empty($options['campaign'])) {
                $campaign = $this->getCampaign($user, $options['campaign']);
                if (!$campaign) {
                    return response()->json([
                        'message' => "❌ Campaign not found: {$options['campaign']}"
                    ]);
                }
            }
            
            $instructions = $this->buildInstructions(
                ['amount' => $amount, 'type' => 'redeemable'],
                $options,
                $campaign
            );
            
            // Generate vouchers using the package action
            $vouchers = BaseGenerateVouchers::run($instructions);
            
            // Format success message
            $codes = $vouchers->pluck('code')->implode(', ');
            $formattedAmount = Number::format($amount, locale: 'en_PH');
            
            $message = sprintf(
                '✅ Voucher(s) %s generated (₱%s)',
                $codes,
                $formattedAmount
            );
            
            if ($vouchers->count() > 1) {
                $message .= sprintf(' • %d vouchers', $vouchers->count());
            }
            
            return response()->json(['message' => $message]);
            
        } catch (\Throwable $e) {
            Log::error('[SMSGenerate] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => '⚠️ Failed to generate voucher. ' . $e->getMessage()
            ]);
        }
    }

    protected function buildInstructions(array $params, array $options, ?Campaign $campaign): VoucherInstructionsData
    {
        $amount = $params['amount'];
        $count = max(1, min(1000, (int) ($options['count'] ?? 1)));
        
        // Start with campaign instructions if available
        if ($campaign) {
            $base = $campaign->instructions->toArray();
            // Override amount, count, and inputs
            $base['cash']['amount'] = $amount;
            $base['count'] = $count;
            
            // Normalize campaign input fields (convert enums to strings)
            if (isset($base['inputs']['fields'])) {
                $base['inputs']['fields'] = $this->normalizeInputFields($base['inputs']['fields']);
            }
            
            // Override inputs if provided via flag
            if (!empty($options['inputs'])) {
                $base['inputs']['fields'] = $this->parseInputFields($options['inputs']);
            }
        } else {
            $base = [
                'cash' => [
                    'amount' => $amount,
                    'currency' => Number::defaultCurrency(),
                    'settlement_rail' => null,
                    'fee_strategy' => 'absorb',
                    'validation' => [
                        'secret' => null,
                        'mobile' => null,
                        'payable' => null,
                        'country' => 'PH',
                        'location' => null,
                        'radius' => null,
                    ],
                ],
                'voucher_type' => null, // redeemable
                'target_amount' => null,
                'inputs' => [
                    'fields' => !empty($options['inputs']) 
                        ? $this->parseInputFields($options['inputs'])
                        : []
                ],
                'feedback' => [
                    'email' => null,
                    'mobile' => null,
                    'webhook' => null,
                ],
                'rider' => [
                    'message' => null,
                    'url' => null,
                    'redirect_timeout' => null,
                    'splash' => null,
                    'splash_timeout' => null,
                ],
                'validation' => null,
                'count' => $count,
                'prefix' => '',
                'mask' => '',
                'ttl' => null,
                'metadata' => null,
            ];
        }
        
        // Apply options from flags (overrides campaign)
        if (!empty($options['prefix'])) {
            $base['prefix'] = $options['prefix'];
        }
        
        if (!empty($options['mask'])) {
            $base['mask'] = $options['mask'];
        }
        
        if (!empty($options['rider-message'])) {
            $base['rider']['message'] = $options['rider-message'];
        }
        
        if (!empty($options['ttl'])) {
            $days = (int) $options['ttl'];
            $base['ttl'] = CarbonInterval::days($days);
        }
        
        if (!empty($options['settlement-rail'])) {
            $rail = strtoupper($options['settlement-rail']);
            if (in_array($rail, ['INSTAPAY', 'PESONET'])) {
                $base['cash']['settlement_rail'] = $rail;
            }
        }
        
        return VoucherInstructionsData::from($base);
    }
}
