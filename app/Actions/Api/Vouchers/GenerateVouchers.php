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
use App\Settings\VoucherSettings;
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
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;

/**
 * Generate Vouchers
 *
 * Create one or more vouchers for disbursement. Each voucher can be redeemed once and will
 * trigger automated disbursement via INSTAPAY or PESONET settlement rails.
 *
 * **Idempotency**: This endpoint requires an `Idempotency-Key` header (UUID recommended).
 * Duplicate requests with the same key will return the cached response.
 *
 * **Financial Safety**: Voucher generation is atomic - all vouchers are created in a single
 * database transaction. If generation fails, no charges are applied to your wallet.
 *
 * @group Vouchers
 * @authenticated
 */
#[Group('Vouchers')]
class GenerateVouchers
{
    use AsAction;

    /**
     * Generate Vouchers
     * 
     * Create one or more vouchers with customizable instructions for disbursement.
     *
     * @operationId generateVouchers
     * @response 201 {"count":2,"vouchers":[{"code":"ABC-1234","amount":100}],"total_amount":200,"currency":"PHP"}
     * @response 400 {"message":"Idempotency-Key header is required for this request."}
     * @response 403 {"message":"Insufficient wallet balance to generate vouchers."}
     * @response 422 {"message":"The given data was invalid.","errors":{"amount":["The amount field is required."]}}
     */
    #[BodyParameter('amount', description: '**REQUIRED**. Voucher amount in major units (whole PHP). Minimum: 0. This is the exact amount that will be disbursed to the redeemer. Example: 500 = ₱500.00', type: 'number', example: 500)]
    #[BodyParameter('count', description: '**REQUIRED**. Number of vouchers to generate. Range: 1-1000. Cannot be 0 (minimum is 1 for production use).', type: 'integer', example: 10)]
    #[BodyParameter('prefix', description: '*optional* - Prefix for voucher codes (1-10 characters). Will be prepended to generated codes. Example: "PROMO" generates "PROMO-AB12CD34".', type: 'string', example: 'PROMO')]
    #[BodyParameter('mask', description: '*optional* - Voucher code pattern. Must contain only asterisks (*) and hyphens (-). Asterisks: 4-6 required (each becomes a random alphanumeric char). Hyphens: used as separators. Example: "****-****" generates "AB12-CD34".', type: 'string', example: '****-****')]
    #[BodyParameter('ttl_days', description: '*optional* - Voucher expiration in days from creation (minimum: 1 day). Omit this field for vouchers that never expire.', type: 'integer', example: 30)]
    #[BodyParameter('input_fields', description: '*optional* - Array of required input fields for redemption. Valid values: "email", "mobile", "name", "address", "birth_date", "gross_monthly_income", "location", "reference_code", "signature", "selfie", "otp", "kyc". Leave empty or omit for no required inputs.', type: 'array', example: ['mobile', 'location', 'selfie'])]
    #[BodyParameter('validation_secret', description: '*optional* - Secret PIN required for redemption. Useful for restricting access.', type: 'string', example: '1234')]
    #[BodyParameter('validation_mobile', description: '*optional* - Assign voucher to specific Philippine mobile number (+639XXXXXXXXX format).', type: 'string', example: '+639171234567')]
    #[BodyParameter('validation_payable', description: '*optional* - Restrict redemption to specific vendor alias (B2B vouchers). Only users with matching vendor alias can redeem.', type: 'string', example: 'TESTSHOP')]
    #[BodyParameter('feedback_email', description: '*optional* - Email address to receive redemption notifications.', type: 'string', example: 'notify@example.com')]
    #[BodyParameter('feedback_mobile', description: '*optional* - Philippine mobile number to receive SMS notifications upon redemption.', type: 'string', example: '+639171234567')]
    #[BodyParameter('feedback_webhook', description: '*optional* - Webhook URL to POST redemption data to your system.', type: 'string', example: 'https://api.example.com/webhooks/voucher-redeemed')]
    #[BodyParameter('rider_message', description: '*optional* - Plain text or HTML message to display after successful redemption. Supports basic HTML tags (<p>, <strong>, <em>, <br>). Minimum 1 character if provided.', type: 'string', example: 'Thank you for participating!')]
    #[BodyParameter('rider_url', description: '*optional* - URL to redirect user to after redemption.', type: 'string', example: 'https://example.com/thank-you')]
    #[BodyParameter('rider_redirect_timeout', description: '*optional* - Seconds to wait before auto-redirecting (0-300). 0 = no auto-redirect.', type: 'integer', example: 5)]
    #[BodyParameter('rider_splash', description: '*optional* - Base64-encoded data URI image to display after redemption. Max size: 51,200 characters (~50KB). Supported formats: PNG, JPEG, GIF, WebP. Format: "data:image/png;base64,iVBORw0KG..." Must include full data URI prefix.', type: 'string', example: 'data:image/png;base64,iVBORw0KG...')]
    #[BodyParameter('rider_splash_timeout', description: '*optional* - Seconds to display splash image before continuing (0-60).', type: 'integer', example: 3)]
    #[BodyParameter('settlement_rail', description: '*optional* - Disbursement method: INSTAPAY (real-time, ≤₱50k) or PESONET (next day, ≤₱1M). Auto-selects if not specified.', type: 'string', example: 'INSTAPAY')]
    #[BodyParameter('fee_strategy', description: '*optional* - How disbursement fees are handled: "absorb" = Issuer pays fee (redeemer gets full amount), "include" = Fee deducted from voucher amount (redeemer gets amount minus fee), "add" = Fee added to disbursement (redeemer gets amount plus fee). Default: "absorb".', type: 'string', example: 'absorb')]
    #[BodyParameter('campaign_id', description: '*optional* - Campaign ID to associate vouchers with for tracking purposes.', type: 'integer', example: 1)]
    #[BodyParameter('preview_enabled', description: '*optional* - Allow voucher preview via inspect endpoint before redemption.', type: 'boolean', example: true)]
    #[BodyParameter('preview_scope', description: '*optional* - Preview data scope: "full" (all details), "requirements_only" (inputs needed), "none" (no preview).', type: 'string', example: 'full')]
    #[BodyParameter('preview_message', description: '*optional* - Custom message to display in voucher inspection/preview.', type: 'string', example: 'This voucher is for event attendees only.')]
    #[BodyParameter('validation_location', description: '*optional* - Location validation settings. Object with: required (bool), target_lat (float -90 to 90), target_lng (float -180 to 180), radius_meters (int 1-10000), on_failure ("block"|"warn"). Enforces GPS radius restrictions.', type: 'object', example: ['required' => true, 'target_lat' => 14.5995, 'target_lng' => 120.9842, 'radius_meters' => 1000, 'on_failure' => 'block'])]
    #[BodyParameter('validation_time', description: '*optional* - Time validation settings. Object with: window (object with start_time HH:mm, end_time HH:mm, timezone), limit_minutes (int 1-1440 max time to redeem after generation), track_duration (bool). Enforces time restrictions.', type: 'object', example: ['limit_minutes' => 1440, 'track_duration' => true])]
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
        
        // Store idempotency key in vouchers (if provided by middleware)
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            foreach ($vouchers as $voucher) {
                $voucher->update([
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_created_at' => now(),
                ]);
            }
        }
        
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
        
        // Escrow is handled automatically by the pipeline via pay() on Cash entities
        // ChargeInstructions pipeline charges any fees (now runs synchronously)

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
            'validation_payable' => 'nullable|string',

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
            
            // Settlement voucher fields
            'voucher_type' => 'nullable|string|in:redeemable,payable,settlement',
            'target_amount' => 'nullable|numeric|min:0|required_if:voucher_type,payable,settlement',
            'rules' => 'nullable|array',

            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            
            // Preview controls
            'preview_enabled' => 'nullable|boolean',
            'preview_scope' => 'nullable|string|in:full,requirements_only,none',
            'preview_message' => 'nullable|string|max:500',
            
            // Location validation
            'validation_location' => 'nullable|array',
            'validation_location.required' => 'nullable|boolean',
            'validation_location.target_lat' => 'required_with:validation_location|numeric|between:-90,90',
            'validation_location.target_lng' => 'required_with:validation_location|numeric|between:-180,180',
            'validation_location.radius_meters' => 'required_with:validation_location|integer|min:1|max:10000',
            'validation_location.on_failure' => 'required_with:validation_location|in:block,warn',
            
            // Time validation
            'validation_time' => 'nullable|array',
            'validation_time.window' => 'nullable|array',
            'validation_time.window.start_time' => 'required_with:validation_time.window|date_format:H:i',
            'validation_time.window.end_time' => 'required_with:validation_time.window|date_format:H:i',
            'validation_time.window.timezone' => 'required_with:validation_time.window|string|timezone',
            'validation_time.limit_minutes' => 'nullable|integer|min:1|max:1440',
            'validation_time.track_duration' => 'nullable|boolean',
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
        
        // Get redemption endpoint from settings
        $settings = app(VoucherSettings::class);
        $redemptionPath = $settings->default_redemption_endpoint ?? '/disburse';
        
        // Get redemption URLs
        $redemptionUrls = [
            'web' => url($redemptionPath),
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
                    'payable' => $validated['validation_payable'] ?? null,
                    'country' => config('instructions.cash.validation_rules.country', 'PH'),
                    'location' => null,
                    'radius' => null,
                ],
                'settlement_rail' => $validated['settlement_rail'] ?? null,
                'fee_strategy' => $validated['fee_strategy'] ?? 'absorb',
            ],
            'voucher_type' => $validated['voucher_type'] ?? null,
            'target_amount' => $validated['target_amount'] ?? null,
            'rules' => $validated['rules'] ?? null,
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
            'validation' => isset($validated['validation_location']) || isset($validated['validation_time']) ? [
                'location' => isset($validated['validation_location']) ? [
                    'required' => $validated['validation_location']['required'] ?? true,
                    'target_lat' => $validated['validation_location']['target_lat'],
                    'target_lng' => $validated['validation_location']['target_lng'],
                    'radius_meters' => $validated['validation_location']['radius_meters'],
                    'on_failure' => $validated['validation_location']['on_failure'],
                ] : null,
                'time' => isset($validated['validation_time']) ? [
                    'window' => isset($validated['validation_time']['window']) ? [
                        'start_time' => $validated['validation_time']['window']['start_time'],
                        'end_time' => $validated['validation_time']['window']['end_time'],
                        'timezone' => $validated['validation_time']['window']['timezone'],
                    ] : null,
                    'limit_minutes' => $validated['validation_time']['limit_minutes'] ?? null,
                    'track_duration' => $validated['validation_time']['track_duration'] ?? true,
                ] : null,
            ] : null,
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
