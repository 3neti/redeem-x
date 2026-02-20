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
        return ['promptCode', 'xray', 'promptMobile', 'confirm', 'promptBankName', 'promptBankAccount', 'finalize'];
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

        // Check if voucher requires user interaction (inputs/validation)
        if ($this->requiresUserInteraction($voucher)) {
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

        // Store voucher info for X-Ray display
        $newState = $state
            ->set('voucher_code', $voucher->code)
            ->set('voucher_amount', $amount)
            ->set('voucher_expiry', $voucher->expires_at?->format('M d, Y'))
            ->set('voucher_rider_message', $voucher->instructions->rider->message ?? null)
            ->set('voucher_rider_url', $voucher->instructions->rider->url ?? null)
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
            "────────────────────",
            "<b>Amount:</b> {$amount}",
            "<b>Expires:</b> {$expiry}",
        ];

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
            $validations[] = 'Secret PIN required 🔐';
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

        // Check for cached phone number (returning user)
        $cachedPhone = $this->getCachedPhone($update->chatId);

        if ($cachedPhone) {
            // Skip to confirmation with cached phone (default to GCash)
            $newState = $state
                ->set('mobile', $cachedPhone)
                ->set('bank_code', BankService::DEFAULT_BANK_CODE)
                ->set('bank_name', BankService::DEFAULT_BANK_NAME)
                ->set('bank_account', $this->formatMobileForAccount($cachedPhone))
                ->advanceTo('confirm');

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

    // Step 2: Prompt for mobile number via contact share (HARD STOP - no text fallback)
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
                ->set('bank_account', $formattedAccount)
                ->advanceTo('confirm');

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
        $bankName = $state->get('bank_name') ?? BankService::DEFAULT_BANK_NAME;
        $bankAccount = $state->get('bank_account') ?? $this->formatMobileForDisplay($state->get('mobile'));

        return NormalizedResponse::html(
            "📋 <b>Confirm Redemption</b>\n\n".
            "<b>{$amount}</b> → <b>{$bankName}:{$bankAccount}</b>"
        )->withInlineButtons([
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
        $bankName = $state->get('bank_name') ?? BankService::DEFAULT_BANK_NAME;
        $bankAccount = $state->get('bank_account') ?? $this->formatMobileForDisplay($state->get('mobile'));

        return NormalizedResponse::html(
            "✅ <b>{$amount}</b> voucher ready!\n\n".
            "Send to <b>{$bankName}:{$bankAccount}</b>?"
        )->withInlineButtons([
            ['text' => '✅ Accept', 'callback_data' => 'accept'],
            ['text' => '✏️ Edit', 'callback_data' => 'edit'],
        ]);
    }

    /**
     * Show edit options menu.
     */
    protected function promptEditOptions(ConversationState $state): NormalizedResponse
    {
        $bankName = $state->get('bank_name') ?? BankService::DEFAULT_BANK_NAME;
        $bankAccount = $state->get('bank_account');

        return NormalizedResponse::html(
            "✏️ <b>What would you like to change?</b>\n\n".
            "Current: <b>{$bankName}:{$bankAccount}</b>"
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

        $this->log('info', 'Executing redemption', [
            'code' => $voucherCode,
            'mobile' => $mobile,
            'bank_code' => $bankCode,
            'bank_account' => $bankAccount,
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

            // Call the redemption action
            $actionRequest = ActionRequest::create('', 'POST', [
                'voucher_code' => $voucherCode,
                'mobile' => $mobile,
                'bank_spec' => $bankSpec,
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

            // Cache phone number for future redemptions (30 days)
            $this->cachePhone($update->chatId, $mobile);

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
     * Check if voucher requires user interaction.
     *
     * Returns true if:
     * - Voucher has input fields to collect (excluding mobile which we collect via contact share)
     * - Voucher has secret validation
     * - Voucher has location validation
     */
    protected function requiresUserInteraction(Voucher $voucher): bool
    {
        $fields = $voucher->instructions->inputs->fields ?? [];
        
        // Filter out 'mobile' since we collect that via contact share
        $otherFields = array_filter($fields, fn ($field) => 
            $field !== VoucherInputField::MOBILE && $field->value !== 'mobile'
        );
        
        $hasOtherInputs = ! empty($otherFields);
        $hasValidation = ($voucher->instructions->cash->validation->secret ?? false)
                      || ($voucher->instructions->cash->validation->location ?? false);

        return $hasOtherInputs || $hasValidation;
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
}
