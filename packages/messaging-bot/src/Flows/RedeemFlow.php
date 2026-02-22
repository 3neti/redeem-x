<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use App\Actions\Api\Redemption\RedeemViaSms;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Services\BankService;
use LBHurtado\MessagingBot\Services\TxtcmdrClient;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Multi-step flow for voucher redemption with X-Ray display and bank selection.
 *
 * Steps:
 * 1. promptCode - Ask for voucher code
 * 2. xray - Display voucher details (X-Ray) with Continue/Exit
 * 3. promptMobile - Ask for payout mobile number via contact share
 * 4. confirm - Confirm redemption details with bank info
 * 5. promptBankName - (optional) User wants to change bank
 * 6. promptBankAccount - (optional) Enter account number for new bank
 * 7. finalize - Execute redemption
 */
class RedeemFlow extends BaseFlow
{
    protected BankService $bankService;

    public function __construct()
    {
        $this->bankService = app(BankService::class);
    }

    public function initialStep(): string
    {
        return 'promptCode';
    }

    public function steps(): array
    {
        return ['promptCode', 'xray', 'promptTextInput', 'promptLocation', 'promptSelfie', 'promptSignature', 'promptKyc', 'promptMobile', 'promptOtp', 'confirm', 'promptBankName', 'promptBankAccount', 'finalize'];
    }

    /**
     * Text input fields the bot can collect (Phase 2).
     * Note: 'secret_pin' is a special field for validation.secret, not an input field.
     * Note: 'otp' is handled separately via txtcmdr API (Phase 7).
     */
    protected const TEXT_INPUT_FIELDS = [
        'name',
        'email',
        'address',
        'birth_date',
        'gross_monthly_income',
        'reference_code',
        'secret_pin', // For cash.validation.secret
    ];

    /**
     * Fields that require web interaction (not collectible via bot).
     * Note: KYC is now collectible via bot with external web link (Phase 5B).
     */
    protected const WEB_ONLY_FIELDS = [
        // All fields now supported via bot
    ];

    /**
     * Get configuration for a text input field.
     */
    protected function getTextInputConfig(VoucherInputField $field): ?array
    {
        return match ($field) {
            VoucherInputField::NAME => [
                'prompt' => "👤 <b>Enter your full name:</b>",
                'validation' => fn($v) => mb_strlen(trim($v)) >= 2,
                'error' => 'Name must be at least 2 characters.',
            ],
            VoucherInputField::EMAIL => [
                'prompt' => "📧 <b>Enter your email address:</b>",
                'validation' => fn($v) => filter_var(trim($v), FILTER_VALIDATE_EMAIL) !== false,
                'error' => 'Please enter a valid email address.',
            ],
            VoucherInputField::ADDRESS => [
                'prompt' => "🏠 <b>Enter your address:</b>",
                'validation' => fn($v) => mb_strlen(trim($v)) >= 10,
                'error' => 'Address must be at least 10 characters.',
            ],
            VoucherInputField::BIRTH_DATE => [
                'prompt' => "📅 <b>Enter your birth date:</b>\n<i>Format: YYYY-MM-DD (e.g., 1990-05-15)</i>",
                'validation' => fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v)) && strtotime(trim($v)) !== false,
                'error' => 'Please enter a valid date in YYYY-MM-DD format.',
            ],
            VoucherInputField::GROSS_MONTHLY_INCOME => [
                'prompt' => "💰 <b>Enter your gross monthly income:</b>\n<i>Numbers only (e.g., 25000)</i>",
                'validation' => fn($v) => is_numeric(str_replace([',', ' '], '', trim($v))) && (float) str_replace([',', ' '], '', trim($v)) > 0,
                'error' => 'Please enter a valid amount (numbers only).',
            ],
            VoucherInputField::REFERENCE_CODE => [
                'prompt' => "🔖 <b>Enter reference code:</b>",
                'validation' => fn($v) => mb_strlen(trim($v)) >= 3,
                'error' => 'Reference code must be at least 3 characters.',
            ],
            // Note: OTP handled separately via txtcmdr API (Phase 7)
            default => null,
        };
    }

    /**
     * Get configuration for secret code (validation.secret, not an input field).
     * 
     * @param string|null $expectedSecret The expected secret from voucher.instructions.cash.validation.secret
     */
    protected function getSecretPinConfig(?string $expectedSecret = null): array
    {
        return [
            'prompt' => "🔐 <b>Enter secret code:</b>",
            'validation' => function($v) use ($expectedSecret) {
                $value = trim($v);
                // Basic length validation
                if (mb_strlen($value) < 4) {
                    return false;
                }
                // If expected secret provided, compare against it
                if ($expectedSecret !== null) {
                    return $value === $expectedSecret;
                }
                return true;
            },
            'error' => $expectedSecret !== null ? 'Invalid secret code. Please try again.' : 'Secret code must be at least 4 characters.',
        ];
    }

    /**
     * Get text input fields required by voucher.
     */
    protected function getTextInputFields(Voucher $voucher): array
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        return array_filter($fields, function ($field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            return in_array($fieldValue, self::TEXT_INPUT_FIELDS, true);
        });
    }

    /**
     * Check if voucher requires web-only interaction.
     */
    protected function requiresWebInteraction(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        // Check for web-only input fields
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if (in_array($fieldValue, self::WEB_ONLY_FIELDS, true)) {
                return true;
            }
        }
        
        // Note: Secret PIN validation is now collectible via bot (Phase 6)
        // Note: Location input is now collectible via Telegram native (Phase 3)
        // Note: Selfie input is now collectible via Telegram photo (Phase 4)
        
        return false;
    }

    /**
     * Check if voucher requires location input.
     */
    protected function requiresLocationInput(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if ($fieldValue === 'location') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if voucher requires selfie input.
     */
    protected function requiresSelfieInput(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if ($fieldValue === 'selfie') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if voucher requires OTP input (Phase 7).
     */
    protected function requiresOtpInput(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if ($fieldValue === 'otp') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if voucher requires signature input (Phase 5).
     */
    protected function requiresSignatureInput(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if ($fieldValue === 'signature') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if voucher requires KYC input (Phase 5B).
     */
    protected function requiresKycInput(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        foreach ($fields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
            if ($fieldValue === 'kyc') {
                return true;
            }
        }
        
        return false;
    }

    // Step 1: Prompt for voucher code
    protected function promptPromptCode(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::html(
            "💳 <b>Enter Pay Code:</b>"
        );
    }

    protected function handlePromptCode(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Handle exit request
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Validate code format
        if (! preg_match('/^[A-Z0-9-]{4,20}$/i', $input)) {
            return [
                'response' => $this->validationError('Invalid voucher code format. Please try again.'),
                'state' => $state,
            ];
        }

        // Find voucher
        $voucher = Voucher::where('code', strtoupper($input))->first();

        if (! $voucher) {
            return [
                'response' => $this->validationError('Voucher not found. Please check the code and try again.'),
                'state' => $state,
            ];
        }

        // Validate voucher status
        if ($voucher->isRedeemed()) {
            return $this->retryableError('This voucher has already been redeemed.', $state);
        }

        if ($voucher->isExpired()) {
            return $this->retryableError('This voucher has expired.', $state);
        }

        // Check voucher type - only REDEEMABLE supported
        if ($voucher->voucher_type !== VoucherType::REDEEMABLE) {
            return $this->retryableError(
                "This voucher type ({$voucher->voucher_type->value}) cannot be redeemed via bot.",
                $state
            );
        }

        // Check if voucher requires web-only interaction (location, selfie, signature, kyc, secret)
        if ($this->requiresWebInteraction($voucher)) {
            $url = config('app.url')."/redeem?code={$voucher->code}";

            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>Web Redemption Required</b>\n\n".
                    "This voucher requires additional information.\n".
                    "Please redeem at:\n<code>{$url}</code>\n\n".
                    "Enter another code or tap Exit."
                )->withInlineButtons([
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        // Get display amount from instructions (stored as float in instructions)
        $amount = $voucher->instructions->cash->amount ?? 0;

        // Get text input fields that need to be collected
        $textInputFields = $this->getTextInputFields($voucher);
        $pendingInputs = array_values(array_map(
            fn($f) => $f instanceof VoucherInputField ? $f->value : (string) $f,
            $textInputFields
        ));

        // Check if secret PIN validation is required (Phase 6)
        // Add 'secret_pin' to pending inputs if voucher has validation.secret
        $expectedSecret = $voucher->instructions->cash->validation->secret ?? null;
        if ($expectedSecret && !in_array('secret_pin', $pendingInputs, true)) {
            // Add secret_pin at the beginning (ask for PIN first)
            array_unshift($pendingInputs, 'secret_pin');
        }

        // Check if location/selfie/signature/kyc/otp inputs are required
        $requiresLocation = $this->requiresLocationInput($voucher);
        $requiresSelfie = $this->requiresSelfieInput($voucher);
        $requiresSignature = $this->requiresSignatureInput($voucher);
        $requiresKyc = $this->requiresKycInput($voucher);
        $requiresOtp = $this->requiresOtpInput($voucher);

        // Store voucher info for X-Ray display
        $newState = $state
            ->set('voucher_code', $voucher->code)
            ->set('voucher_amount', $amount)
            ->set('voucher_expiry', $voucher->expires_at?->format('M d, Y'))
            ->set('voucher_rider_message', $voucher->instructions->rider->message ?? null)
            ->set('voucher_rider_url', $voucher->instructions->rider->url ?? null)
            ->set('pending_inputs', $pendingInputs)
            ->set('requires_location', $requiresLocation)
            ->set('requires_selfie', $requiresSelfie)
            ->set('requires_signature', $requiresSignature)
            ->set('requires_kyc', $requiresKyc)
            ->set('requires_otp', $requiresOtp)
            ->set('collected_inputs', [])
            ->set('expected_secret', $expectedSecret) // Store for validation in handlePromptTextInput
            ->advanceTo('xray');

        // Show X-Ray display
        return [
            'response' => $this->buildXRayDisplay($voucher),
            'state' => $newState,
        ];
    }

    /**
     * Build the X-Ray display showing voucher details.
     */
    protected function buildXRayDisplay(Voucher $voucher): NormalizedResponse
    {
        $amount = $this->formatMoney($voucher->instructions->cash->amount ?? 0);
        $expiry = $voucher->expires_at?->format('M d, Y') ?? 'No expiry';
        
        $lines = [
            "💳 <b>Voucher: {$voucher->code}</b>",
            "",
            "💰 {$amount}",
            "📅 {$expiry}",
        ];

        // Add required inputs info (text fields + location + selfie + signature + kyc + otp)
        $textInputFields = $this->getTextInputFields($voucher);
        $requiresLocation = $this->requiresLocationInput($voucher);
        $requiresSelfie = $this->requiresSelfieInput($voucher);
        $requiresSignature = $this->requiresSignatureInput($voucher);
        $requiresKyc = $this->requiresKycInput($voucher);
        $requiresOtp = $this->requiresOtpInput($voucher);
        
        if (! empty($textInputFields) || $requiresLocation || $requiresSelfie || $requiresSignature || $requiresKyc || $requiresOtp) {
            $lines[] = '';
            $lines[] = '<b>Required Info:</b>';
            foreach ($textInputFields as $field) {
                $fieldValue = $field instanceof VoucherInputField ? $field->value : (string) $field;
                $label = $this->getFieldLabel($fieldValue);
                $lines[] = "• {$label}";
            }
            if ($requiresLocation) {
                $lines[] = "• 📍 Location";
            }
            if ($requiresSelfie) {
                $lines[] = "• 📸 Selfie";
            }
            if ($requiresSignature) {
                $lines[] = "• ✍️ Signature";
            }
            if ($requiresKyc) {
                $lines[] = "• 🪪 Identity Verification";
            }
            if ($requiresOtp) {
                $lines[] = "• 🔐 OTP Verification";
            }
        }

        // Add validation info
        $validations = $this->getValidationInfo($voucher);
        if (! empty($validations)) {
            $lines[] = '';
            $lines[] = '<b>Validation:</b>';
            foreach ($validations as $validation) {
                $lines[] = "• {$validation}";
            }
        }

        // Add rider message if present
        $riderMessage = $voucher->instructions->rider->message ?? null;
        if ($riderMessage) {
            $lines[] = '';
            $lines[] = '<b>Message:</b>';
            // Truncate long messages
            $displayMessage = strlen($riderMessage) > 100 
                ? substr($riderMessage, 0, 97) . '...' 
                : $riderMessage;
            $lines[] = "<i>\"{$displayMessage}\"</i>";
        }

        // Add rider URL if present
        $riderUrl = $voucher->instructions->rider->url ?? null;
        if ($riderUrl) {
            $lines[] = '';
            $lines[] = "🔗 <a href=\"{$riderUrl}\">More info</a>";
        }

        return NormalizedResponse::html(implode("\n", $lines))
            ->withInlineButtons([
                ['text' => '✅ Continue', 'callback_data' => 'continue'],
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]);
    }

    /**
     * Get validation info for X-Ray display.
     */
    protected function getValidationInfo(Voucher $voucher): array
    {
        $validations = [];

        // Check secret validation
        if ($voucher->instructions->cash->validation->secret ?? false) {
            $validations[] = 'Secret code required 🔐';
        }

        // Check location validation (from cash.validation or validation.location)
        if (($voucher->instructions->cash->validation->location ?? false) ||
            ($voucher->instructions->validation->location->required ?? false)) {
            $validations[] = 'Location verification 📍';
        }

        // Check time validation
        if ($voucher->instructions->validation->time ?? false) {
            $window = $voucher->instructions->validation->time->window ?? null;
            if ($window) {
                $validations[] = "Time window: {$window->start_time} - {$window->end_time} ⏰";
            }
        }

        return $validations;
    }

    /**
     * Handle X-Ray display response (Continue or Exit).
     */
    protected function handleXray(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        if ($response === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        if ($response !== 'continue') {
            // Remind user of available options
            return [
                'response' => NormalizedResponse::html(
                    "Please tap <b>Continue</b> to proceed or <b>Exit</b> to cancel."
                )->withInlineButtons([
                    ['text' => '✅ Continue', 'callback_data' => 'continue'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        // Check if there are pending text inputs to collect
        $pendingInputs = $state->get('pending_inputs', []);
        if (! empty($pendingInputs)) {
            $newState = $state->advanceTo('promptTextInput');
            return [
                'response' => $this->promptPromptTextInput($newState),
                'state' => $newState,
            ];
        }

        // No text inputs needed - proceed to mobile/confirmation
        return $this->advanceToMobileOrConfirm($update, $state);
    }

    /**
     * Advance to location, selfie, mobile prompt, or confirmation.
     */
    protected function advanceToMobileOrConfirm(NormalizedUpdate $update, ConversationState $state): array
    {
        $collectedInputs = $state->get('collected_inputs', []);

        // Check if location is required and not yet collected
        $requiresLocation = $state->get('requires_location', false);
        if ($requiresLocation && !isset($collectedInputs['location'])) {
            $newState = $state->advanceTo('promptLocation');
            return [
                'response' => $this->promptPromptLocation($newState),
                'state' => $newState,
            ];
        }

        // Check if selfie is required and not yet collected
        $requiresSelfie = $state->get('requires_selfie', false);
        if ($requiresSelfie && !isset($collectedInputs['selfie'])) {
            $newState = $state->advanceTo('promptSelfie');
            return [
                'response' => $this->promptPromptSelfie($newState),
                'state' => $newState,
            ];
        }

        // Check if signature is required and not yet collected (Phase 5)
        $requiresSignature = $state->get('requires_signature', false);
        if ($requiresSignature && !isset($collectedInputs['signature'])) {
            $newState = $state->advanceTo('promptSignature');
            return [
                'response' => $this->promptPromptSignature($newState),
                'state' => $newState,
            ];
        }

        // Check if KYC is required and not yet collected (Phase 5B)
        $requiresKyc = $state->get('requires_kyc', false);
        if ($requiresKyc && !isset($collectedInputs['kyc'])) {
            $newState = $state->advanceTo('promptKyc');
            return [
                'response' => $this->promptPromptKyc($newState),
                'state' => $newState,
            ];
        }

        // Check for cached phone number (returning user)
        $cachedPhone = $this->getCachedPhone($update->chatId);

        if ($cachedPhone) {
            // Load cached bank account or default to GCash with mobile
            $cachedBankAccount = $this->getCachedBankAccount($update->chatId);
            $bankCode = $cachedBankAccount['code'] ?? BankService::DEFAULT_BANK_CODE;
            $bankName = $cachedBankAccount['name'] ?? BankService::DEFAULT_BANK_NAME;
            $bankAccount = $cachedBankAccount['account'] ?? $this->formatMobileForAccount($cachedPhone);

            $newState = $state
                ->set('mobile', $cachedPhone)
                ->set('bank_code', $bankCode)
                ->set('bank_name', $bankName)
                ->set('bank_account', $bankAccount);

            // Check if OTP is required (Phase 7) - also applies to returning users
            if ($state->get('requires_otp', false) && !isset($collectedInputs['otp'])) {
                return $this->triggerOtpAndAdvance($update, $newState);
            }

            // No OTP needed - go straight to confirm
            $newState = $newState->advanceTo('confirm');

            return [
                'response' => $this->promptConfirmWithCachedPhone($newState),
                'state' => $newState,
            ];
        }

        // First-time user - ask for phone via contact share
        $newState = $state->advanceTo('promptMobile');

        return [
            'response' => $this->promptPromptMobile($newState),
            'state' => $newState,
        ];
    }

    // ==================== TEXT INPUT COLLECTION (Phase 2) ====================

    /**
     * Prompt for the current text input field.
     */
    protected function promptPromptTextInput(ConversationState $state): NormalizedResponse
    {
        $pendingInputs = $state->get('pending_inputs', []);
        
        if (empty($pendingInputs)) {
            // Shouldn't happen, but fallback gracefully
            return NormalizedResponse::text("No inputs required. Continuing...");
        }

        $currentField = $pendingInputs[0];
        
        // Handle secret_pin specially (not a VoucherInputField enum)
        if ($currentField === 'secret_pin') {
            $config = $this->getSecretPinConfig($state->get('expected_secret'));
        } else {
            $fieldEnum = VoucherInputField::tryFrom($currentField);
            $config = $fieldEnum ? $this->getTextInputConfig($fieldEnum) : null;
        }

        if (! $config) {
            // Skip unknown field type
            return NormalizedResponse::text("Unknown field: {$currentField}");
        }

        return NormalizedResponse::html($config['prompt'])
            ->withInlineButtons([
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]);
    }

    /**
     * Handle text input submission.
     */
    protected function handlePromptTextInput(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        $pendingInputs = $state->get('pending_inputs', []);
        $collectedInputs = $state->get('collected_inputs', []);

        if (empty($pendingInputs)) {
            // All inputs collected - advance to mobile
            return $this->advanceToMobileOrConfirm($update, $state);
        }

        $currentField = $pendingInputs[0];
        
        // Handle secret_pin specially (not a VoucherInputField enum)
        if ($currentField === 'secret_pin') {
            $config = $this->getSecretPinConfig($state->get('expected_secret'));
        } else {
            $fieldEnum = VoucherInputField::tryFrom($currentField);
            $config = $fieldEnum ? $this->getTextInputConfig($fieldEnum) : null;
        }

        if (! $config) {
            // Skip unknown field
            array_shift($pendingInputs);
            $newState = $state->set('pending_inputs', $pendingInputs);
            
            if (empty($pendingInputs)) {
                return $this->advanceToMobileOrConfirm($update, $newState);
            }
            
            return [
                'response' => $this->promptPromptTextInput($newState),
                'state' => $newState,
            ];
        }

        // Validate input
        $value = trim($input);
        $isValid = ($config['validation'])($value);

        if (! $isValid) {
            return [
                'response' => NormalizedResponse::html(
                    "❌ {$config['error']}\n\n".
                    $config['prompt']
                )->withInlineButtons([
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        // Store the collected input and move to next
        $collectedInputs[$currentField] = $this->normalizeInputValue($currentField, $value);
        array_shift($pendingInputs);

        $newState = $state
            ->set('pending_inputs', $pendingInputs)
            ->set('collected_inputs', $collectedInputs);

        // Check if more inputs to collect
        if (! empty($pendingInputs)) {
            return [
                'response' => $this->promptPromptTextInput($newState),
                'state' => $newState,
            ];
        }

        // All inputs collected - advance to mobile/confirm
        return $this->advanceToMobileOrConfirm($update, $newState);
    }

    /**
     * Normalize input value based on field type.
     */
    protected function normalizeInputValue(string $field, string $value): string|float
    {
        return match ($field) {
            'gross_monthly_income' => (float) str_replace([',', ' '], '', $value),
            default => $value,
        };
    }

    /**
     * Get human-readable label for a field.
     */
    protected function getFieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Full Name',
            'email' => 'Email Address',
            'mobile' => 'Mobile Number',
            'reference_code' => 'Reference Code',
            'address' => 'Residential Address',
            'birth_date' => 'Birth Date',
            'gross_monthly_income' => 'Gross Monthly Income',
            'otp' => 'OTP',
            'location' => 'Location',
            'selfie' => 'Selfie Photo',
            'signature' => 'Signature',
            'kyc' => 'Identity Verification',
            'secret_pin' => '🔐 Secret Code',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    // ==================== LOCATION COLLECTION (Phase 3) ====================

    /**
     * Prompt for location via Telegram's native location sharing.
     */
    protected function promptPromptLocation(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::html(
            "📍 <b>Share your location</b>\n\n".
            "Tap the button below to share your GPS location.\n".
            "This is required for verification.\n\n".
            "<i>Type 'exit' to cancel.</i>"
        )->withLocationRequest();
    }

    /**
     * Handle location sharing response.
     */
    protected function handlePromptLocation(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Debug: Log what we received
        $this->log('info', 'handlePromptLocation called', [
            'input' => $input,
            'hasLocation' => $update->hasLocation(),
            'latitude' => $update->latitude,
            'longitude' => $update->longitude,
            'rawPayload' => $update->rawPayload,
        ]);

        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Check if user shared their location
        if ($update->hasLocation()) {
            $collectedInputs = $state->get('collected_inputs', []);
            
            // Store as {lat, lng} format (matches LocationSpecification expectation)
            $collectedInputs['location'] = [
                'lat' => $update->latitude,
                'lng' => $update->longitude,
            ];

            $this->log('info', 'Received location from Telegram', [
                'latitude' => $update->latitude,
                'longitude' => $update->longitude,
            ]);

            $newState = $state->set('collected_inputs', $collectedInputs);

            // Proceed to mobile/confirm
            return $this->advanceToMobileOrConfirm($update, $newState);
        }

        // User typed text instead of sharing location
        return [
            'response' => NormalizedResponse::html(
                "⚠️ <b>Please use the Share Location button</b>\n\n".
                "For verification, we need your GPS location.\n".
                "Tap <b>📍 Share Location</b> below.\n\n".
                "<i>Type 'exit' to cancel.</i>"
            )->withLocationRequest(),
            'state' => $state,
        ];
    }

    // ==================== SELFIE COLLECTION (Phase 4) ====================

    /**
     * Prompt for selfie via Mini App button or direct photo.
     *
     * Shows inline buttons:
     * - WebApp button to open selfie capture Mini App
     * - Continue button to check if selfie was uploaded via Mini App
     */
    protected function promptPromptSelfie(ConversationState $state): NormalizedResponse
    {
        $miniAppUrl = $this->getSelfieCaptureMiniAppUrl($state);

        // Use inline keyboard with WebApp button and Continue button
        return NormalizedResponse::html(
            "📸 <b>Take a selfie</b>\n\n".
            "1️⃣ Tap <b>Take Selfie</b> to open the camera\n".
            "2️⃣ After uploading, tap <b>Continue</b>\n\n".
            "<i>You can also send a photo directly, or type 'exit' to cancel.</i>"
        )->withWebAppButton('📸 Take Selfie', $miniAppUrl)
         ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'selfie_continue']]);
    }

    /**
     * Get the selfie capture Mini App URL with chat_id parameter.
     *
     * Falls back to APP_URL/bot/selfie-capture if not explicitly configured.
     */
    protected function getSelfieCaptureMiniAppUrl(ConversationState $state): string
    {
        $baseUrl = config('messaging-bot.mini_app.selfie_url')
            ?? rtrim(config('app.url'), '/').'/bot/selfie-capture';

        // Append chat_id as query parameter
        $chatId = $state->chatId;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'chat_id='.$chatId;
    }

    /**
     * Handle selfie photo response.
     *
     * Checks for:
     * 1. Cached selfie from Mini App (triggered by selfie_uploaded message)
     * 2. Direct photo message
     */
    protected function handlePromptSelfie(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $this->log('info', 'handlePromptSelfie called', [
            'chat_id' => $update->chatId,
            'input' => $input,
            'has_photo' => $update->hasPhoto(),
        ]);

        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Check for cached selfie from Mini App
        // This is triggered when user taps "Continue" after using the Mini App
        $cachedSelfie = $this->getCachedSelfie($update->chatId);
        
        $this->log('info', 'Checking cached selfie', [
            'chat_id' => $update->chatId,
            'has_cached' => $cachedSelfie !== null,
            'cached_size' => $cachedSelfie ? strlen($cachedSelfie) : 0,
        ]);

        if ($cachedSelfie) {
            $this->log('info', 'Retrieved selfie from Mini App cache', [
                'chat_id' => $update->chatId,
                'size' => strlen($cachedSelfie),
            ]);

            // Clear the cache
            $this->clearCachedSelfie($update->chatId);

            $collectedInputs = $state->get('collected_inputs', []);
            $collectedInputs['selfie'] = $cachedSelfie;

            $newState = $state->set('collected_inputs', $collectedInputs);

            // Proceed to mobile/confirm
            return $this->advanceToMobileOrConfirm($update, $newState);
        }

        // Check if user sent a photo directly
        if ($update->hasPhoto()) {
            $this->log('info', 'Received photo from Telegram', [
                'file_id' => $update->photoFileId,
            ]);

            try {
                // Download photo and convert to base64
                $driver = app(\LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver::class);
                $base64 = $driver->downloadFileAsBase64($update->photoFileId);

                $collectedInputs = $state->get('collected_inputs', []);
                $collectedInputs['selfie'] = $base64;

                $this->log('info', 'Selfie captured and stored', [
                    'size' => strlen($base64),
                ]);

                $newState = $state->set('collected_inputs', $collectedInputs);

                // Proceed to mobile/confirm
                return $this->advanceToMobileOrConfirm($update, $newState);

            } catch (\Throwable $e) {
                $this->log('error', 'Failed to download selfie', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'response' => NormalizedResponse::html(
                        "❌ <b>Failed to process photo</b>\n\n".
                        "Please try sending another photo.\n\n".
                        "<i>Type 'exit' to cancel.</i>"
                    ),
                    'state' => $state,
                ];
            }
        }

        // User typed text or tapped Continue but no cached selfie found
        // Re-prompt with instructions
        $miniAppUrl = $this->getSelfieCaptureMiniAppUrl($state);

        // Check if user tapped Continue without uploading
        if ($input === 'selfie_continue') {
            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>No selfie found</b>\n\n".
                    "Please take a selfie first, then tap Continue.\n\n".
                    "<i>Type 'exit' to cancel.</i>"
                )->withWebAppButton('📸 Take Selfie', $miniAppUrl)
                 ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'selfie_continue']]),
                'state' => $state,
            ];
        }

        return [
            'response' => NormalizedResponse::html(
                "⚠️ <b>Please take a selfie</b>\n\n".
                "1️⃣ Tap <b>Take Selfie</b> to open the camera\n".
                "2️⃣ After uploading, tap <b>Continue</b>\n\n".
                "<i>You can also send a photo directly, or type 'exit' to cancel.</i>"
            )->withWebAppButton('📸 Take Selfie', $miniAppUrl)
             ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'selfie_continue']]),
            'state' => $state,
        ];
    }

    /**
     * Get cached selfie from Mini App.
     */
    protected function getCachedSelfie(string $chatId): ?string
    {
        return \App\Http\Controllers\Bot\SelfieCaptureController::getCachedSelfie($chatId);
    }

    /**
     * Clear cached selfie from Mini App.
     */
    protected function clearCachedSelfie(string $chatId): void
    {
        \App\Http\Controllers\Bot\SelfieCaptureController::clearCachedSelfie($chatId);
    }

    // ==================== SIGNATURE COLLECTION (Phase 5) ====================

    /**
     * Prompt for signature via Mini App button.
     *
     * Shows inline buttons:
     * - WebApp button to open signature capture Mini App
     * - Continue button to check if signature was uploaded via Mini App
     */
    protected function promptPromptSignature(ConversationState $state): NormalizedResponse
    {
        $miniAppUrl = $this->getSignatureCaptureMiniAppUrl($state);

        // Use inline keyboard with WebApp button and Continue button
        return NormalizedResponse::html(
            "✍️ <b>Sign your name</b>\n\n".
            "1️⃣ Tap <b>Sign</b> to open the signature pad\n".
            "2️⃣ Draw your signature\n".
            "3️⃣ After uploading, tap <b>Continue</b>\n\n".
            "<i>Type 'exit' to cancel.</i>"
        )->withWebAppButton('✍️ Sign', $miniAppUrl)
         ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'signature_continue']]);
    }

    /**
     * Get the signature capture Mini App URL with chat_id parameter.
     *
     * Falls back to APP_URL/bot/signature-capture if not explicitly configured.
     */
    protected function getSignatureCaptureMiniAppUrl(ConversationState $state): string
    {
        $baseUrl = config('messaging-bot.mini_app.signature_url')
            ?? rtrim(config('app.url'), '/').'/bot/signature-capture';

        // Append chat_id as query parameter
        $chatId = $state->chatId;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'chat_id='.$chatId;
    }

    /**
     * Handle signature response.
     *
     * Checks for cached signature from Mini App (triggered by Continue button).
     */
    protected function handlePromptSignature(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $this->log('info', 'handlePromptSignature called', [
            'chat_id' => $update->chatId,
            'input' => $input,
        ]);

        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Check for cached signature from Mini App
        // This is triggered when user taps "Continue" after using the Mini App
        $cachedSignature = $this->getCachedSignature($update->chatId);
        
        $this->log('info', 'Checking cached signature', [
            'chat_id' => $update->chatId,
            'has_cached' => $cachedSignature !== null,
            'cached_size' => $cachedSignature ? strlen($cachedSignature) : 0,
        ]);

        if ($cachedSignature) {
            $this->log('info', 'Retrieved signature from Mini App cache', [
                'chat_id' => $update->chatId,
                'size' => strlen($cachedSignature),
            ]);

            // Clear the cache
            $this->clearCachedSignature($update->chatId);

            $collectedInputs = $state->get('collected_inputs', []);
            $collectedInputs['signature'] = $cachedSignature;

            $newState = $state->set('collected_inputs', $collectedInputs);

            // Proceed to mobile/confirm
            return $this->advanceToMobileOrConfirm($update, $newState);
        }

        // User tapped Continue but no cached signature found
        // Re-prompt with instructions
        $miniAppUrl = $this->getSignatureCaptureMiniAppUrl($state);

        // Check if user tapped Continue without uploading
        if ($input === 'signature_continue') {
            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>No signature found</b>\n\n".
                    "Please sign first, then tap Continue.\n\n".
                    "<i>Type 'exit' to cancel.</i>"
                )->withWebAppButton('✍️ Sign', $miniAppUrl)
                 ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'signature_continue']]),
                'state' => $state,
            ];
        }

        return [
            'response' => NormalizedResponse::html(
                "⚠️ <b>Please sign your name</b>\n\n".
                "1️⃣ Tap <b>Sign</b> to open the signature pad\n".
                "2️⃣ Draw your signature\n".
                "3️⃣ After uploading, tap <b>Continue</b>\n\n".
                "<i>Type 'exit' to cancel.</i>"
            )->withWebAppButton('✍️ Sign', $miniAppUrl)
             ->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'signature_continue']]),
            'state' => $state,
        ];
    }

    /**
     * Get cached signature from Mini App.
     */
    protected function getCachedSignature(string $chatId): ?string
    {
        return \App\Http\Controllers\Bot\SignatureCaptureController::getCachedSignature($chatId);
    }

    /**
     * Clear cached signature from Mini App.
     */
    protected function clearCachedSignature(string $chatId): void
    {
        \App\Http\Controllers\Bot\SignatureCaptureController::clearCachedSignature($chatId);
    }

    // ==================== KYC VERIFICATION (Phase 5B) ====================

    /**
     * Prompt for KYC verification via external web link.
     *
     * Shows URL button to open KYC initiate page in browser + Continue button.
     */
    protected function promptPromptKyc(ConversationState $state): NormalizedResponse
    {
        $url = $this->getKycInitiateUrl($state);

        return NormalizedResponse::html(
            "🪪 <b>Identity Verification Required</b>\n\n".
            "Please verify your identity to proceed.\n\n".
            "1️⃣ Tap <b>Verify Identity</b> to start\n".
            "2️⃣ Complete verification in browser\n".
            "3️⃣ Return here and tap <b>Continue</b>\n\n".
            "<i>Type 'exit' to cancel.</i>"
        )->withInlineButtons([
            ['text' => '🪪 Verify Identity', 'url' => $url],
            ['text' => '✅ Continue', 'callback_data' => 'kyc_continue'],
        ]);
    }

    /**
     * Get the KYC initiate URL with voucher and chat_id parameters.
     */
    protected function getKycInitiateUrl(ConversationState $state): string
    {
        $voucherCode = $state->get('voucher_code');
        $chatId = $state->chatId;
        $mobile = $state->get('mobile');

        $params = http_build_query([
            'voucher' => $voucherCode,
            'chat_id' => $chatId,
            'mobile' => $mobile,
        ]);

        return rtrim(config('app.url'), '/') . '/bot/kyc/initiate?' . $params;
    }

    /**
     * Handle KYC verification response.
     *
     * Checks cached KYC status from KYCController (triggered by Continue button).
     */
    protected function handlePromptKyc(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $this->log('info', 'handlePromptKyc called', [
            'chat_id' => $update->chatId,
            'input' => $input,
        ]);

        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Check for cached KYC status from KYCController
        $kycStatus = $this->getKycStatus($update->chatId);

        $this->log('info', 'Checking KYC status', [
            'chat_id' => $update->chatId,
            'has_status' => $kycStatus !== null,
            'status' => $kycStatus['status'] ?? null,
        ]);

        if ($kycStatus) {
            $status = $kycStatus['status'] ?? 'pending';

            // Handle approved status
            if ($status === 'approved') {
                $this->log('info', 'KYC approved', [
                    'chat_id' => $update->chatId,
                    'transaction_id' => $kycStatus['transaction_id'] ?? null,
                ]);

                // Clear the cache
                $this->clearKycCache($update->chatId);

                $collectedInputs = $state->get('collected_inputs', []);
                $collectedInputs['kyc'] = [
                    'status' => 'approved',
                    'transaction_id' => $kycStatus['transaction_id'] ?? null,
                    'contact_id' => $kycStatus['contact_id'] ?? null,
                ];

                $newState = $state->set('collected_inputs', $collectedInputs);

                // Proceed to mobile/confirm
                return $this->advanceToMobileOrConfirm($update, $newState);
            }

            // Handle rejected status
            if ($status === 'rejected') {
                $this->log('warning', 'KYC rejected', [
                    'chat_id' => $update->chatId,
                ]);

                // Clear the cache to allow retry
                $this->clearKycCache($update->chatId);

                $url = $this->getKycInitiateUrl($state);

                return [
                    'response' => NormalizedResponse::html(
                        "❌ <b>Verification Failed</b>\n\n".
                        "Your identity verification was not approved.\n".
                        "Please try again with clear photos.\n\n".
                        "<i>Type 'exit' to cancel.</i>"
                    )->withInlineButtons([
                        ['text' => '🪪 Retry Verification', 'url' => $url],
                        ['text' => '✅ Continue', 'callback_data' => 'kyc_continue'],
                    ]),
                    'state' => $state,
                ];
            }

            // Handle cancelled status
            if ($status === 'cancelled') {
                $this->clearKycCache($update->chatId);

                $url = $this->getKycInitiateUrl($state);

                return [
                    'response' => NormalizedResponse::html(
                        "🚫 <b>Verification Cancelled</b>\n\n".
                        "You cancelled the verification process.\n".
                        "Tap below to try again when ready.\n\n".
                        "<i>Type 'exit' to cancel.</i>"
                    )->withInlineButtons([
                        ['text' => '🪪 Verify Identity', 'url' => $url],
                        ['text' => '✅ Continue', 'callback_data' => 'kyc_continue'],
                    ]),
                    'state' => $state,
                ];
            }

            // Handle processing status
            if ($status === 'processing') {
                return [
                    'response' => NormalizedResponse::html(
                        "⏳ <b>Verification In Progress</b>\n\n".
                        "Your verification is being processed.\n".
                        "Please wait a moment and tap Continue again.\n\n".
                        "<i>Type 'exit' to cancel.</i>"
                    )->withInlineButtons([['text' => '✅ Continue', 'callback_data' => 'kyc_continue']]),
                    'state' => $state,
                ];
            }
        }

        // No status found or still pending - prompt to start verification
        $url = $this->getKycInitiateUrl($state);

        // Check if user tapped Continue without starting verification
        if ($input === 'kyc_continue') {
            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>Verification Not Started</b>\n\n".
                    "Please tap <b>Verify Identity</b> first,\n".
                    "complete the process, then tap Continue.\n\n".
                    "<i>Type 'exit' to cancel.</i>"
                )->withInlineButtons([
                    ['text' => '🪪 Verify Identity', 'url' => $url],
                    ['text' => '✅ Continue', 'callback_data' => 'kyc_continue'],
                ]),
                'state' => $state,
            ];
        }

        return [
            'response' => NormalizedResponse::html(
                "⚠️ <b>Please verify your identity</b>\n\n".
                "1️⃣ Tap <b>Verify Identity</b> to start\n".
                "2️⃣ Complete verification in browser\n".
                "3️⃣ Return here and tap <b>Continue</b>\n\n".
                "<i>Type 'exit' to cancel.</i>"
            )->withInlineButtons([
                ['text' => '🪪 Verify Identity', 'url' => $url],
                ['text' => '✅ Continue', 'callback_data' => 'kyc_continue'],
            ]),
            'state' => $state,
        ];
    }

    /**
     * Get KYC status from cache.
     */
    protected function getKycStatus(string $chatId): ?array
    {
        return \App\Http\Controllers\Bot\KYCController::getKycStatus($chatId);
    }

    /**
     * Clear KYC cache.
     */
    protected function clearKycCache(string $chatId): void
    {
        \App\Http\Controllers\Bot\KYCController::clearKycCache($chatId);
    }

    // ==================== MOBILE COLLECTION ====================

    // Step: Prompt for mobile number via contact share (HARD STOP - no text fallback)
    protected function promptPromptMobile(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::html(
            "📱 <b>Share your mobile number</b>\n\n".
            "Tap the button below to share your contact.\n".
            "This ensures we send funds to the right account."
        )->withContactRequest();
    }

    protected function handlePromptMobile(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Check if user shared their contact (phone number from Telegram)
        if ($update->hasPhoneNumber()) {
            $mobile = $this->normalizePhone($update->phoneNumber);

            $this->log('info', 'Received phone from contact share', [
                'phone' => $mobile,
            ]);

            // Default to GCash with mobile number as account
            $formattedAccount = $this->formatMobileForAccount($mobile);

            $newState = $state
                ->set('mobile', $mobile)
                ->set('bank_code', BankService::DEFAULT_BANK_CODE)
                ->set('bank_name', BankService::DEFAULT_BANK_NAME)
                ->set('bank_account', $formattedAccount);

            // Check if OTP is required (Phase 7)
            if ($state->get('requires_otp', false)) {
                return $this->triggerOtpAndAdvance($update, $newState);
            }

            // No OTP needed - go straight to confirm
            $newState = $newState->advanceTo('confirm');

            return [
                'response' => $this->promptConfirm($newState),
                'state' => $newState,
            ];
        }

        // HARD STOP: User typed text instead of sharing contact
        return [
            'response' => NormalizedResponse::html(
                "⚠️ <b>Please use the Share button</b>\n\n".
                "For your security, we need you to share your contact directly.\n".
                "Tap <b>📱 Share Contact</b> below."
            )->withContactRequest()->withInlineButtons([
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]),
            'state' => $state,
        ];
    }

    // ==================== OTP VERIFICATION (Phase 7) ====================

    /**
     * Trigger OTP request and advance to promptOtp step.
     */
    protected function triggerOtpAndAdvance(NormalizedUpdate $update, ConversationState $state): array
    {
        $mobile = $state->get('mobile');
        $voucherCode = $state->get('voucher_code');

        try {
            $client = new TxtcmdrClient();
            $result = $client->requestOtp($mobile, $voucherCode);

            $this->log('info', 'OTP requested via txtcmdr', [
                'mobile' => $mobile,
                'verification_id' => $result['verification_id'],
                'dev_code' => $result['dev_code'] ?? null,
            ]);

            $newState = $state
                ->set('otp_verification_id', $result['verification_id'])
                ->set('otp_resend_count', 0)
                ->advanceTo('promptOtp');

            return [
                'response' => $this->promptPromptOtp($newState, $result['dev_code'] ?? null),
                'state' => $newState,
            ];

        } catch (\Exception $e) {
            $this->log('error', 'Failed to request OTP', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            // Fall back to confirm without OTP (graceful degradation)
            $newState = $state->advanceTo('confirm');

            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>OTP service unavailable</b>\n\n".
                    "Proceeding without OTP verification."
                ),
                'state' => $newState,
                'followup' => $this->promptConfirm($newState),
            ];
        }
    }

    /**
     * Prompt for OTP code entry.
     */
    protected function promptPromptOtp(ConversationState $state, ?string $devCode = null): NormalizedResponse
    {
        $mobile = $state->get('mobile');
        $maskedMobile = $this->maskMobile($mobile);

        $text = "🔐 <b>Enter OTP Code</b>\n\n".
            "A 6-digit code was sent to <b>{$maskedMobile}</b>\n\n".
            "Enter the code below:";

        // Show dev code in local/testing environment
        if ($devCode && (app()->isLocal() || app()->environment('testing'))) {
            $text .= "\n\n<i>🧪 Dev code: <code>{$devCode}</code></i>";
        }

        return NormalizedResponse::html($text)
            ->withInlineButtons([
                ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]);
    }

    /**
     * Handle OTP submission or resend request.
     */
    protected function handlePromptOtp(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Handle exit
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Handle resend request
        if ($input === 'otp_resend') {
            return $this->handleOtpResend($update, $state);
        }

        // Validate OTP format (6 digits)
        $code = trim($input);
        if (! preg_match('/^\d{4,6}$/', $code)) {
            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Invalid code format</b>\n\n".
                    "Please enter the 6-digit code sent to your phone."
                )->withInlineButtons([
                    ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        // Verify OTP via txtcmdr
        $verificationId = $state->get('otp_verification_id');

        try {
            $client = new TxtcmdrClient();
            $result = $client->verifyOtp($verificationId, $code);

            if ($result['ok']) {
                $this->log('info', 'OTP verified successfully', [
                    'verification_id' => $verificationId,
                ]);

                // Store OTP in collected inputs and advance to confirm
                $collectedInputs = $state->get('collected_inputs', []);
                $collectedInputs['otp'] = $code;

                $newState = $state
                    ->set('collected_inputs', $collectedInputs)
                    ->advanceTo('confirm');

                // Build confirm prompt with success prefix
                $confirmPrompt = $this->promptConfirm($newState);
                $successMessage = "✅ <b>OTP verified!</b>\n\n";

                return [
                    'response' => NormalizedResponse::html($successMessage . $confirmPrompt->text)
                        ->withKeyboardRemoved()
                        ->withInlineButtons($confirmPrompt->buttons),
                    'state' => $newState,
                ];
            }

            // OTP verification failed
            $errorMessages = [
                'invalid_code' => 'The OTP code is incorrect.',
                'expired' => 'The OTP code has expired. Please request a new one.',
                'locked' => 'Too many failed attempts. Please request a new OTP.',
                'already_verified' => 'This OTP has already been used.',
                'not_found' => 'Verification session expired. Please request a new OTP.',
            ];

            $errorMessage = $errorMessages[$result['reason']] ?? 'OTP verification failed.';
            $attemptsInfo = isset($result['attempts']) ? " (Attempt {$result['attempts']})" : '';

            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>{$errorMessage}</b>{$attemptsInfo}\n\n".
                    "Please try again or request a new code."
                )->withInlineButtons([
                    ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];

        } catch (\Exception $e) {
            $this->log('error', 'OTP verification API error', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);

            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Verification service error</b>\n\n".
                    "Please try again or request a new code."
                )->withInlineButtons([
                    ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }
    }

    /**
     * Handle OTP resend request.
     */
    protected function handleOtpResend(NormalizedUpdate $update, ConversationState $state): array
    {
        $resendCount = $state->get('otp_resend_count', 0);
        $maxResends = config('otp-handler.max_resends', 3);

        if ($resendCount >= $maxResends) {
            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Maximum resends reached</b>\n\n".
                    "You have reached the maximum number of OTP resends.\n".
                    "Please restart the redemption process."
                )->withInlineButtons([
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        $mobile = $state->get('mobile');
        $voucherCode = $state->get('voucher_code');

        try {
            $client = new TxtcmdrClient();
            $result = $client->requestOtp($mobile, $voucherCode);

            $this->log('info', 'OTP resent via txtcmdr', [
                'mobile' => $mobile,
                'verification_id' => $result['verification_id'],
                'resend_count' => $resendCount + 1,
            ]);

            $newState = $state
                ->set('otp_verification_id', $result['verification_id'])
                ->set('otp_resend_count', $resendCount + 1);

            $remaining = $maxResends - $resendCount - 1;
            $resendInfo = $remaining > 0 ? "({$remaining} resends remaining)" : "(last resend)";

            return [
                'response' => NormalizedResponse::html(
                    "✅ <b>New OTP sent!</b> {$resendInfo}\n\n".
                    "Enter the 6-digit code sent to your phone."
                )->withInlineButtons([
                    ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $newState,
            ];

        } catch (\Exception $e) {
            $this->log('error', 'Failed to resend OTP', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Failed to resend OTP</b>\n\n".
                    "Please try again in a moment."
                )->withInlineButtons([
                    ['text' => '🔄 Resend OTP', 'callback_data' => 'otp_resend'],
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }
    }

    /**
     * Mask mobile number for display (e.g., +63917***1234).
     */
    protected function maskMobile(string $mobile): string
    {
        $len = strlen($mobile);
        if ($len <= 7) {
            return $mobile;
        }
        return substr($mobile, 0, 6) . '***' . substr($mobile, -4);
    }

    /**
     * Normalize phone to +63 format.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+63'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '+')) {
            return '+'.$phone;
        }

        return $phone;
    }

    /**
     * Format mobile number for use as bank account (09xx format).
     */
    protected function formatMobileForAccount(string $mobile): string
    {
        $mobile = ltrim($mobile, '+');
        if (str_starts_with($mobile, '63')) {
            return '0' . substr($mobile, 2);
        }
        return $mobile;
    }

    // Step 3: Confirm redemption with bank details
    protected function promptConfirm(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));
        $bankDisplay = $this->formatBankDisplay($state);

        return NormalizedResponse::html(
            "📋 <b>Confirm Redemption</b>\n\n".
            "<b>{$amount}</b> → {$bankDisplay}"
        )->withKeyboardRemoved()->withInlineButtons([
            ['text' => '✅ Accept', 'callback_data' => 'accept'],
            ['text' => '✏️ Edit', 'callback_data' => 'edit'],
        ]);
    }

    /**
     * Confirm prompt for returning users (with cached phone).
     */
    protected function promptConfirmWithCachedPhone(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));
        $bankDisplay = $this->formatBankDisplay($state);

        return NormalizedResponse::html(
            "✅ <b>{$amount}</b> voucher ready!\n\n".
            "Send to {$bankDisplay}?"
        )->withKeyboardRemoved()->withInlineButtons([
            ['text' => '✅ Accept', 'callback_data' => 'accept'],
            ['text' => '✏️ Edit', 'callback_data' => 'edit'],
        ]);
    }

    /**
     * Format bank display with red highlight if account differs from mobile.
     */
    protected function formatBankDisplay(ConversationState $state): string
    {
        $bankName = $state->get('bank_name') ?? BankService::DEFAULT_BANK_NAME;
        $bankAccount = $state->get('bank_account') ?? $this->formatMobileForDisplay($state->get('mobile'));
        $mobileAsAccount = $this->formatMobileForAccount($state->get('mobile'));

        // If account matches mobile, show in normal bold
        if ($bankAccount === $mobileAsAccount) {
            return "<b>{$bankName}:{$bankAccount}</b>";
        }

        // Account differs from mobile - highlight in red (using <code> with emoji indicator)
        return "⚠️ <b>{$bankName}:<u>{$bankAccount}</u></b>";
    }

    /**
     * Show edit options menu.
     */
    protected function promptEditOptions(ConversationState $state): NormalizedResponse
    {
        $bankDisplay = $this->formatBankDisplay($state);

        return NormalizedResponse::html(
            "✏️ <b>What would you like to change?</b>\n\n".
            "Current: {$bankDisplay}"
        )->withInlineButtons([
            ['text' => '🏦 Bank', 'callback_data' => 'change_bank'],
            ['text' => '💳 Acct', 'callback_data' => 'change_account'],
            ['text' => '↩️ Back', 'callback_data' => 'back'],
        ]);
    }

    protected function handleConfirm(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        // Handle exit
        if ($response === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // User wants to edit - show options menu
        if ($response === 'edit') {
            return [
                'response' => $this->promptEditOptions($state),
                'state' => $state,
            ];
        }

        // User wants to change bank (from edit menu)
        if ($response === 'change_bank') {
            $newState = $state->advanceTo('promptBankName');
            return [
                'response' => $this->promptPromptBankName($newState),
                'state' => $newState,
            ];
        }

        // User wants to change just the account number (from edit menu)
        if ($response === 'change_account') {
            $newState = $state->advanceTo('promptBankAccount');
            return [
                'response' => $this->promptPromptBankAccount($newState),
                'state' => $newState,
            ];
        }

        // User wants to change phone number (from edit menu)
        if (in_array($response, ['change_number', 'change', 'no', 'n', 'cancel'])) {
            // Clear cached phone and bank info
            $newState = $state
                ->set('mobile', null)
                ->set('bank_code', null)
                ->set('bank_name', null)
                ->set('bank_account', null)
                ->advanceTo('promptMobile');

            return [
                'response' => $this->promptPromptMobile($newState),
                'state' => $newState,
            ];
        }

        // Back from edit menu
        if ($response === 'back') {
            return [
                'response' => $this->promptConfirm($state),
                'state' => $state,
            ];
        }

        // Accept - proceed to finalize
        if (in_array($response, ['accept', 'yes', 'y', 'confirm'])) {
            $newState = $state->advanceTo('finalize');
            return $this->handleFinalize($update, $newState, $input);
        }

        // Invalid response - show buttons again
        return [
            'response' => NormalizedResponse::html(
                "Please tap <b>Accept</b> or <b>Edit</b>."
            )->withInlineButtons([
                ['text' => '✅ Accept', 'callback_data' => 'accept'],
                ['text' => '✏️ Edit', 'callback_data' => 'edit'],
            ]),
            'state' => $state,
        ];
    }

    // Step 5: Prompt for bank name (Change Bank flow)
    protected function promptPromptBankName(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::html(
            "🏦 <b>Enter bank name</b>\n\n".
            "Type the name of your bank or e-wallet:\n".
            "<i>Examples: BPI, BDO, Maya, UnionBank, SeaBank</i>"
        )->withInlineButtons([
            ['text' => '↩️ Back', 'callback_data' => 'back'],
        ]);
    }

    protected function handlePromptBankName(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower(trim($input));

        // Handle back
        if ($response === 'back') {
            $newState = $state->advanceTo('confirm');
            return [
                'response' => $this->promptConfirm($newState),
                'state' => $newState,
            ];
        }

        // Handle exit
        if ($response === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Fuzzy match the bank
        $matches = $this->bankService->fuzzyMatch($input, 3);

        if (empty($matches)) {
            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Bank not found</b>\n\n".
                    "Try another name or check the spelling.\n".
                    "<i>Examples: GCash, Maya, BPI, BDO</i>"
                )->withInlineButtons([
                    ['text' => '↩️ Back', 'callback_data' => 'back'],
                ]),
                'state' => $state,
            ];
        }

        // Single match - use it directly
        if (count($matches) === 1) {
            $bank = $matches[0];
            $newState = $state
                ->set('bank_code', $bank['code'])
                ->set('bank_name', $bank['name'])
                ->advanceTo('promptBankAccount');

            return [
                'response' => $this->promptPromptBankAccount($newState),
                'state' => $newState,
            ];
        }

        // Multiple matches - let user select
        $buttons = [];
        foreach ($matches as $index => $bank) {
            $buttons[] = ['text' => $bank['name'], 'callback_data' => "bank_{$index}"];
        }
        $buttons[] = ['text' => '↩️ Back', 'callback_data' => 'back'];

        // Store matches in state for selection
        $newState = $state->set('bank_matches', $matches);

        return [
            'response' => NormalizedResponse::html(
                "🏦 <b>Select your bank:</b>"
            )->withInlineButtons($buttons),
            'state' => $newState,
        ];
    }

    // Additional handler for bank selection from fuzzy matches
    protected function handleBankSelection(ConversationState $state, int $index): array
    {
        $matches = $state->get('bank_matches') ?? [];

        if (! isset($matches[$index])) {
            // Invalid selection - go back to bank name prompt
            return [
                'response' => $this->promptPromptBankName($state),
                'state' => $state->advanceTo('promptBankName'),
            ];
        }

        $bank = $matches[$index];
        $newState = $state
            ->set('bank_code', $bank['code'])
            ->set('bank_name', $bank['name'])
            ->set('bank_matches', null) // Clear matches
            ->advanceTo('promptBankAccount');

        return [
            'response' => $this->promptPromptBankAccount($newState),
            'state' => $newState,
        ];
    }

    // Step 6: Prompt for bank account number
    protected function promptPromptBankAccount(ConversationState $state): NormalizedResponse
    {
        $bankName = $state->get('bank_name');
        $currentAccount = $state->get('bank_account');

        // If we have a current account, offer to keep it
        if ($currentAccount) {
            return NormalizedResponse::html(
                "📝 <b>Enter account number</b>\n\n".
                "Current: <code>{$currentAccount}</code>\n".
                "Tap below to keep it, or type a different number."
            )->withInlineButtons([
                ['text' => "✅ Keep {$currentAccount}", 'callback_data' => 'keep_account'],
                ['text' => '↩️ Back', 'callback_data' => 'back'],
            ]);
        }

        // No current account - just ask for one
        return NormalizedResponse::html(
            "📝 <b>Enter {$bankName} account number</b>\n\n".
            "Type your account number:"
        )->withInlineButtons([
            ['text' => '↩️ Back', 'callback_data' => 'back'],
        ]);
    }

    protected function handlePromptBankAccount(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower(trim($input));

        // Handle back
        if ($response === 'back') {
            $newState = $state->advanceTo('promptBankName');
            return [
                'response' => $this->promptPromptBankName($newState),
                'state' => $newState,
            ];
        }

        // Handle exit
        if ($response === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Handle "keep account" - user wants to keep current account
        if ($response === 'keep_account') {
            $newState = $state->advanceTo('confirm');
            return [
                'response' => $this->promptConfirm($newState),
                'state' => $newState,
            ];
        }

        // Bank selection from fuzzy match results
        if (preg_match('/^bank_(\d+)$/', $response, $matches)) {
            return $this->handleBankSelection($state, (int) $matches[1]);
        }

        // Validate account number format (basic validation)
        $account = preg_replace('/[^0-9]/', '', $input);

        if (strlen($account) < 8 || strlen($account) > 16) {
            return [
                'response' => NormalizedResponse::html(
                    "❌ <b>Invalid account number</b>\n\n".
                    "Please enter a valid account number (8-16 digits)."
                )->withInlineButtons([
                    ['text' => '↩️ Back', 'callback_data' => 'back'],
                ]),
                'state' => $state,
            ];
        }

        // Store account and go to confirmation
        $newState = $state
            ->set('bank_account', $account)
            ->advanceTo('confirm');

        return [
            'response' => $this->promptConfirm($newState),
            'state' => $newState,
        ];
    }

    // Step 7: Execute redemption
    protected function handleFinalize(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $voucherCode = $state->get('voucher_code');
        $mobile = $state->get('mobile');
        $bankCode = $state->get('bank_code') ?? BankService::DEFAULT_BANK_CODE;
        $bankName = $state->get('bank_name') ?? BankService::DEFAULT_BANK_NAME;
        $bankAccount = $state->get('bank_account') ?? $this->formatMobileForAccount($mobile);
        $collectedInputs = $state->get('collected_inputs', []);

        $this->log('info', 'Executing redemption', [
            'code' => $voucherCode,
            'mobile' => $mobile,
            'bank_code' => $bankCode,
            'bank_account' => $bankAccount,
            'collected_inputs' => array_keys($collectedInputs),
            'platform' => $update->platform->value,
        ]);

        try {
            // Re-validate voucher before redemption
            $voucher = Voucher::where('code', $voucherCode)->first();

            if (! $voucher || $voucher->isRedeemed()) {
                return $this->complete(
                    NormalizedResponse::text('❌ Voucher is no longer available for redemption.')
                );
            }

            // Build bank_spec for redemption action
            $bankSpec = "{$bankCode}:{$bankAccount}";

            // Extract secret_pin from collected inputs (passed separately, not in inputs array)
            $secret = $collectedInputs['secret_pin'] ?? null;
            $inputsWithoutSecret = array_filter(
                $collectedInputs,
                fn($key) => $key !== 'secret_pin',
                ARRAY_FILTER_USE_KEY
            );

            // Call the redemption action with collected inputs
            $actionRequest = ActionRequest::create('', 'POST', [
                'voucher_code' => $voucherCode,
                'mobile' => $mobile,
                'bank_spec' => $bankSpec,
                'secret' => $secret, // Pass secret separately for SecretSpecification
                'inputs' => $inputsWithoutSecret,
            ]);

            $response = app(RedeemViaSms::class)->asController($actionRequest);
            $result = json_decode($response->getContent(), true);

            if (! ($result['success'] ?? false)) {
                $errorMessage = $result['message'] ?? 'Redemption failed';

                // Handle specific error cases
                if (($result['error'] ?? '') === 'requires_web') {
                    return $this->complete(
                        NormalizedResponse::html(
                            "⚠️ <b>Web Redemption Required</b>\n\n".
                            "This voucher needs additional information.\n".
                            "Please redeem at:\n".
                            "<code>{$result['redemption_url']}</code>"
                        )
                    );
                }

                return $this->complete(
                    NormalizedResponse::text("❌ {$errorMessage}")
                );
            }

            // Success!
            $voucherData = $result['data']['voucher'] ?? [];
            $amount = $this->formatMoney($voucherData['amount'] ?? $state->get('voucher_amount'));

            // Cache phone and bank account for future redemptions (30 days)
            $this->cachePhone($update->chatId, $mobile);
            $this->cacheBankAccount($update->chatId, $bankCode, $bankName, $bankAccount);

            Log::info('[RedeemFlow] Redemption successful via messaging', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'bank_code' => $bankCode,
                'bank_account' => $bankAccount,
                'platform' => $update->platform->value,
                'chat_id' => $update->chatId,
            ]);

            // Build success message with rider info
            $successLines = [
                "🎉 <b>Done!</b>",
                "",
                "{$amount} is on the way to your {$bankName}.",
                "Account: {$bankAccount}",
            ];

            // Add rider message if present
            $riderMessage = $state->get('voucher_rider_message');
            if ($riderMessage) {
                $successLines[] = '';
                $successLines[] = '💬 ' . $riderMessage;
            }

            // Add rider URL if present
            $riderUrl = $state->get('voucher_rider_url');
            if ($riderUrl) {
                $successLines[] = '';
                $successLines[] = "🔗 <a href=\"{$riderUrl}\">More info</a>";
            }

            return $this->complete(
                NormalizedResponse::html(implode("\n", $successLines))
            );

        } catch (\Throwable $e) {
            Log::error('[RedeemFlow] Redemption failed', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Show detailed error in non-production
            $errorMessage = "❌ Redemption failed. Please try again later.\n\n";
            
            if (! app()->isProduction()) {
                $errorMessage .= "Error: {$e->getMessage()}\n";
                $errorMessage .= "File: {$e->getFile()}:{$e->getLine()}\n\n";
            }
            
            $errorMessage .= "If the problem persists, contact support.";

            return $this->complete(
                NormalizedResponse::text($errorMessage)
            );
        }
    }

    /**
     * Format amount as money.
     */
    protected function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            return Money::of($amount, $currency)->formatTo('en_PH');
        } catch (\Throwable $e) {
            return '₱'.number_format($amount, 2);
        }
    }

    /**
     * Format mobile number for display (e.g., 0917xxxxxxx format).
     */
    protected function formatMobileForDisplay(string $mobile): string
    {
        // Remove + prefix and convert +63 to 0 for PH numbers
        $mobile = ltrim($mobile, '+');

        if (str_starts_with($mobile, '63')) {
            return '0'.substr($mobile, 2);
        }

        return $mobile;
    }

    /**
     * Return a retryable error that keeps the user in the flow.
     */
    protected function retryableError(string $message, ConversationState $state): array
    {
        return [
            'response' => NormalizedResponse::html(
                "❌ {$message}\n\n".
                "Enter another code or tap Exit."
            )->withInlineButtons([
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]),
            'state' => $state,
        ];
    }

    /**
     * Get cached phone number for a chat.
     */
    protected function getCachedPhone(string $chatId): ?string
    {
        return Cache::get($this->phoneCacheKey($chatId));
    }

    /**
     * Cache phone number for a chat.
     */
    protected function cachePhone(string $chatId, string $phone): void
    {
        Cache::put($this->phoneCacheKey($chatId), $phone, now()->addDays(30));
    }

    /**
     * Get cache key for phone number.
     */
    protected function phoneCacheKey(string $chatId): string
    {
        return "messaging:phone:{$chatId}";
    }

    /**
     * Get cached bank account for a chat.
     */
    protected function getCachedBankAccount(string $chatId): ?array
    {
        return Cache::get($this->bankAccountCacheKey($chatId));
    }

    /**
     * Cache bank account for a chat.
     */
    protected function cacheBankAccount(string $chatId, string $code, string $name, string $account): void
    {
        Cache::put($this->bankAccountCacheKey($chatId), [
            'code' => $code,
            'name' => $name,
            'account' => $account,
        ], now()->addDays(30));
    }

    /**
     * Get cache key for bank account.
     */
    protected function bankAccountCacheKey(string $chatId): string
    {
        return "messaging:bank_account:{$chatId}";
    }
}
