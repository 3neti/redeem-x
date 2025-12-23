<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

/**
 * IMPORTANT: This is the PRIMARY voucher generation endpoint used by the Vue.js frontend.
 * 
 * The web form at /vouchers/generate uses this API endpoint (POST /api/v1/vouchers)
 * via the useVoucherApi composable, NOT the web controller.
 * 
 * There is also a web controller (App\Http\Controllers\Vouchers\GenerateController)
 * that is currently unused but exists for backward compatibility.
 * 
 * When adding new fields (like rider_splash), update BOTH controllers to avoid confusion.
 */

use App\Actions\Billing\CalculateCharge;
use App\Http\Responses\ApiResponse;
use App\Models\Campaign;
use App\Models\CampaignVoucher;
use App\Models\VoucherGenerationCharge;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Actions\GenerateVouchers as BaseGenerateVouchers;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\VoucherMetadataData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\DataCollection;

/**
 * Generate vouchers via API.
 *
 * Endpoint: POST /api/v1/vouchers
 */
class GenerateVouchers
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        // Get validated data (ActionRequest auto-validates with rules() method)
        $validated = $request->validated();

        // Check if user has sufficient balance
        $amount = $validated['amount'];
        $count = $validated['count'];
        $totalCost = $amount * $count;

        if ($request->user()->balanceFloatNum < $totalCost) {
            return ApiResponse::forbidden('Insufficient wallet balance to generate vouchers.');
        }

        // Convert request to instructions
        $instructions = $this->toInstructions($validated);

        // Generate vouchers using package action
        $vouchers = BaseGenerateVouchers::run($instructions);
        
        // Calculate and create billing record
        $calculateCharge = app(CalculateCharge::class);
        $breakdown = $calculateCharge->handle($request->user(), $instructions);
        
        VoucherGenerationCharge::create([
            'user_id' => $request->user()->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'voucher_codes' => $vouchers->pluck('code')->toArray(),
            'voucher_count' => $vouchers->count(),
            'instructions_snapshot' => $instructions->toArray(),
            'charge_breakdown' => $breakdown->breakdown,
            'total_charge' => $breakdown->total / 100,
            'charge_per_voucher' => ($breakdown->total / $vouchers->count()) / 100,
            'generated_at' => now(),
        ]);

        // Attach vouchers to campaign if campaign_id provided
        if (!empty($validated['campaign_id'])) {
            $campaign = Campaign::find($validated['campaign_id']);
            if ($campaign && $campaign->user_id === $request->user()->id) {
                foreach ($vouchers as $voucher) {
                    CampaignVoucher::create([
                        'campaign_id' => $campaign->id,
                        'voucher_id' => $voucher->id,
                        'instructions_snapshot' => $campaign->instructions->toArray(),
                    ]);
                }
            }
        }

        // Transform to VoucherData DTOs using DataCollection
        $voucherData = new DataCollection(VoucherData::class, $vouchers->all());

        // Calculate totals
        $totalAmount = $vouchers->sum(fn ($v) => $v->instructions->cash->amount ?? 0);

        return ApiResponse::created([
            'count' => $vouchers->count(),
            'vouchers' => $voucherData,
            'total_amount' => $totalAmount,
            'currency' => $instructions->cash->currency ?? 'PHP',
        ]);
    }


    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'count' => 'required|integer|min:1|max:1000',
            'prefix' => 'nullable|string|min:1|max:10',
            'mask' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (! preg_match("/^[\\*\\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    $asterisks = substr_count($value, '*');

                    if ($asterisks < 4) {
                        $fail('The :attribute must contain at least 4 asterisks (*).');
                    }

                    if ($asterisks > 6) {
                        $fail('The :attribute must contain at most 6 asterisks (*).');
                    }
                },
            ],
            'ttl_days' => 'nullable|integer|min:1',

            'input_fields' => 'nullable|array',
            'input_fields.*' => ['nullable', 'string', 'in:'.implode(',', VoucherInputField::values())],

            'validation_secret' => 'nullable|string',
            'validation_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],

            'feedback_email' => 'nullable|email',
            'feedback_mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback_webhook' => 'nullable|url',

            'rider_message' => 'nullable|string|min:1',
            'rider_url' => 'nullable|url',
            'rider_redirect_timeout' => 'nullable|integer|min:0|max:300',
            'rider_splash' => 'nullable|string|max:51200',
            'rider_splash_timeout' => 'nullable|integer|min:0|max:60',
            
            // Settlement rail and fee strategy
            'settlement_rail' => 'nullable|string|in:INSTAPAY,PESONET',
            'fee_strategy' => 'nullable|string|in:absorb,include,add',

            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            
            // Preview controls
            'preview_enabled' => 'nullable|boolean',
            'preview_scope' => 'nullable|string|in:full,requirements_only,none',
            'preview_message' => 'nullable|string|max:500',
        ];
    }

    /**
     * Convert validated data to VoucherInstructionsData.
     */
    protected function toInstructions(array $validated): VoucherInstructionsData
    {
        // Parse input_fields if it's JSON string
        $inputFields = $validated['input_fields'] ?? [];
        if (is_string($inputFields)) {
            $inputFields = json_decode($inputFields, true) ?? [];
        }

        // Convert ttl_days to CarbonInterval
        $ttl = null;
        if (! empty($validated['ttl_days'])) {
            $ttl = CarbonInterval::days($validated['ttl_days']);
        }

        // Get authenticated user for metadata
        $owner = auth()->user();
        
        // Get redemption URLs
        $redemptionUrls = [
            'web' => route('redeem.start'),
        ];
        
        // Add API endpoint if route exists
        if (Route::has('api.redemption.validate')) {
            $redemptionUrls['api'] = route('api.redemption.validate');
        }
        
        // Add widget URL if configured
        if ($widgetUrl = config('voucher.redemption.widget_url')) {
            $redemptionUrls['widget'] = $widgetUrl;
        }
        
        // Determine primary URL (prefer web)
        $primaryUrl = $redemptionUrls['web'] ?? null;
        
        // Collect active licenses (non-null values)
        $licenses = array_filter(config('voucher.metadata.licenses', []));
        
        // Create metadata with preview controls
        $metadata = VoucherMetadataData::from([
            'version' => config('voucher.metadata.version'),
            'system_name' => config('voucher.metadata.system_name'),
            'copyright' => config('voucher.metadata.copyright'),
            'licenses' => $licenses,
            'issuer_id' => $owner->id,
            'issuer_name' => $owner->name ?? $owner->email,
            'issuer_email' => $owner->email,
            'redemption_urls' => $redemptionUrls,
            'primary_url' => $primaryUrl,
            'created_at' => now(),
            'issued_at' => now(),
            
            // Optional fields (signature support)
            'public_key' => config('voucher.security.enable_signatures') 
                ? config('voucher.security.public_key') 
                : null,
            
            // Preview controls from request
            // Use array_key_exists to properly handle false boolean values
            'preview_enabled' => array_key_exists('preview_enabled', $validated) ? $validated['preview_enabled'] : true,
            'preview_scope' => $validated['preview_scope'] ?? 'full',
            'preview_message' => $validated['preview_message'] ?? null,
        ]);
        
        $data_array = [
            'cash' => [
                'amount' => $validated['amount'],
                'currency' => Number::defaultCurrency(),
                'validation' => [
                    'secret' => $validated['validation_secret'] ?? null,
                    'mobile' => $validated['validation_mobile'] ?? null,
                    'country' => config('instructions.cash.validation_rules.country', 'PH'),
                    'location' => null,
                    'radius' => null,
                ],
                'settlement_rail' => $validated['settlement_rail'] ?? null,
                'fee_strategy' => $validated['fee_strategy'] ?? 'absorb',
            ],
            'inputs' => [
                'fields' => $inputFields,
            ],
            'feedback' => [
                'email' => $validated['feedback_email'] ?? null,
                'mobile' => $validated['feedback_mobile'] ?? null,
                'webhook' => $validated['feedback_webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider_message'] ?? null,
                'url' => $validated['rider_url'] ?? null,
                'redirect_timeout' => $validated['rider_redirect_timeout'] ?? null,
                'splash' => $validated['rider_splash'] ?? null,
                'splash_timeout' => $validated['rider_splash_timeout'] ?? null,
            ],
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? '',
            'mask' => $validated['mask'] ?? '',
            'ttl' => $ttl,
            'metadata' => $metadata,
        ];

        return VoucherInstructionsData::from($data_array);
    }

    /**
     * Custom validation messages.
     */
    public function getValidationMessages(): array
    {
        return [
            'amount.required' => 'Voucher amount is required.',
            'amount.min' => 'Voucher amount must be at least 0.',
            'count.required' => 'Voucher count is required.',
            'count.min' => 'You must generate at least 1 voucher.',
            'count.max' => 'You cannot generate more than 1000 vouchers at once.',
        ];
    }

}
