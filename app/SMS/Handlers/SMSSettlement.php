<?php

namespace App\SMS\Handlers;

use LBHurtado\Voucher\Actions\GenerateVouchers as BaseGenerateVouchers;
use App\Models\Campaign;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * SMS handler for generating SETTLEMENT vouchers.
 * 
 * Supports command:
 * - SETTLEMENT {amount} {target} [--flags]
 * 
 * Settlement vouchers have an initial amount and a target amount.
 */
class SMSSettlement extends BaseSMSVoucherHandler
{
    protected function getInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED),
            new InputArgument('amount', InputArgument::REQUIRED),
            new InputArgument('target', InputArgument::REQUIRED),
            new InputOption('count', null, InputOption::VALUE_REQUIRED, 'Number of vouchers', 1),
            new InputOption('campaign', null, InputOption::VALUE_REQUIRED, 'Campaign name'),
            new InputOption('rider-message', null, InputOption::VALUE_REQUIRED, 'Rider message'),
            new InputOption('prefix', null, InputOption::VALUE_REQUIRED, 'Voucher code prefix'),
            new InputOption('mask', null, InputOption::VALUE_REQUIRED, 'Voucher code mask'),
            new InputOption('ttl', null, InputOption::VALUE_REQUIRED, 'TTL in days'),
            new InputOption('settlement-rail', null, InputOption::VALUE_REQUIRED, 'Settlement rail (instapay/pesonet/auto)'),
        ]);
    }

    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        // User already authenticated by middleware
        $user = request()->user();
        
        try {
            // Router already extracted {amount} and {target} from pattern
            $amount = (float) ($values['amount'] ?? 0);
            $target = (float) ($values['target'] ?? 0);
            
            // Options from flags (TODO: implement flag parsing from message)
            $options = [];
            
            if ($amount <= 0 || $target <= 0) {
                return response()->json([
                    'message' => '❌ Invalid amounts. Both amount and target must be greater than 0.'
                ]);
            }
            
            if ($target < $amount) {
                return response()->json([
                    'message' => '❌ Target amount must be greater than or equal to initial amount.'
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
                ['amount' => $amount, 'target' => $target, 'type' => 'settlement'],
                $options,
                $campaign
            );
            
            $vouchers = BaseGenerateVouchers::run($instructions);
            
            $codes = $vouchers->pluck('code')->implode(', ');
            $formattedAmount = Number::format($amount, locale: 'en_PH');
            $formattedTarget = Number::format($target, locale: 'en_PH');
            
            $message = sprintf(
                '✅ Settlement voucher(s) %s generated (₱%s → ₱%s)',
                $codes,
                $formattedAmount,
                $formattedTarget
            );
            
            if ($vouchers->count() > 1) {
                $message .= sprintf(' • %d vouchers', $vouchers->count());
            }
            
            return response()->json(['message' => $message]);
            
        } catch (\Throwable $e) {
            Log::error('[SMSSettlement] Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => '⚠️ Failed to generate settlement voucher. ' . $e->getMessage()
            ]);
        }
    }

    protected function buildInstructions(array $params, array $options, ?Campaign $campaign): VoucherInstructionsData
    {
        $amount = $params['amount'];
        $target = $params['target'];
        $count = max(1, min(1000, (int) ($options['count'] ?? 1)));
        
        if ($campaign) {
            $base = $campaign->instructions->toArray();
            $base['voucher_type'] = VoucherType::SETTLEMENT->value;
            $base['cash']['amount'] = $amount;
            $base['target_amount'] = $target;
            $base['count'] = $count;
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
                'voucher_type' => VoucherType::SETTLEMENT->value,
                'target_amount' => $target,
                'inputs' => ['fields' => []],
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
        
        // Apply options from flags
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
            $base['cash']['settlement_rail'] = strtolower($options['settlement-rail']);
        }
        
        return VoucherInstructionsData::from($base);
    }
}
