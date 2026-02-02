<?php

use LBHurtado\OmniChannel\Middlewares\{AutoReplySMS, CleanSMS, LogSMS, RateLimitSMS, StoreSMS};
use LBHurtado\OmniChannel\Handlers\{SMSAutoRegister, SMSBalance, SMSHelp, SMSRegister};
use App\SMS\Handlers\{SMSGenerate, SMSPayable, SMSSettlement};
use LBHurtado\OmniChannel\Services\SMSRouterService;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Enums\VoucherType;
use App\Actions\Api\Redemption\RedeemViaSms;
use Lorisleiva\Actions\ActionRequest;

/** @var SMSRouterService $router */
$router = resolve(SMSRouterService::class);
//Log::info("✅  Resolved SMSRouterService instance.", ['instance' => get_class($router)]);

$router->register('REGISTER {mobile?} {extra?}', SMSRegister::class);
$router->register('REG {email} {extra?}', SMSAutoRegister::class);
$router->register('BALANCE {flag?}', SMSBalance::class);
$router->register('HELP {command?}', SMSHelp::class);

// Voucher generation commands (must be registered before catchall)
// {extra?} captures optional flags like --count=3 --campaign="Name"
$router->register('GENERATE {amount} {extra?}', SMSGenerate::class);
$router->register('REDEEMABLE {amount} {extra?}', SMSGenerate::class);
$router->register('PAYABLE {amount} {extra?}', SMSPayable::class);
$router->register('SETTLEMENT {amount} {target} {extra?}', SMSSettlement::class);

$router->register(
    '{message}',
    function ($values, $from, $to) {
        $message = trim($values['message']);
        
        Log::info("📩 SMS Catchall Matched", ['message' => $message, 'from' => $from]);
        
        // Check if message looks like a voucher code (4-20 chars, alphanumeric + hyphens)
        if (!preg_match('/^[A-Z0-9-]{4,20}$/i', $message)) {
            // Not a voucher code pattern - ignore
            Log::info("🔍 Not a voucher code pattern, ignoring", ['message' => $message]);
            return response()->json(['message' => null]);
        }
        
        $voucherCode = strtoupper($message);
        
        // Try to find voucher
        $voucher = Voucher::where('code', $voucherCode)->first();
        
        if (!$voucher) {
            // Not a valid voucher code - ignore (don't leak information)
            Log::info("🔍 Voucher not found, ignoring", ['code' => $voucherCode]);
            return response()->json(['message' => null]);
        }
        
        Log::info("✅ Voucher found", [
            'code' => $voucherCode,
            'type' => $voucher->voucher_type->value,
            'redeemed' => $voucher->isRedeemed(),
            'expired' => $voucher->isExpired(),
        ]);
        
        // Check voucher type - only support REDEEMABLE in Phase 1
        if ($voucher->voucher_type !== VoucherType::REDEEMABLE) {
            Log::info("⚠️ Non-REDEEMABLE voucher type", ['type' => $voucher->voucher_type->value]);
            return response()->json([
                'message' => "⚠️ SMS redemption for this voucher type is under construction. Please use the web to redeem."
            ]);
        }
        
        // Check if voucher requires user interaction
        $hasInputs = !empty($voucher->instructions->inputs->fields ?? []);
        $hasValidation = $voucher->instructions->cash->validation->secret 
                      || $voucher->instructions->cash->validation->location;
        
        if ($hasInputs || $hasValidation) {
            Log::info("⚠️ Voucher requires user interaction", [
                'has_inputs' => $hasInputs,
                'has_validation' => $hasValidation,
            ]);
            return response()->json([
                'message' => "⚠️ This voucher requires additional information. Please redeem via web: " . config('app.url') . "/disburse?code={$voucherCode}"
            ]);
        }
        
        // Check voucher status
        if ($voucher->isRedeemed()) {
            return response()->json([
                'message' => "❌ This voucher has already been redeemed."
            ]);
        }
        
        if ($voucher->isExpired()) {
            return response()->json([
                'message' => "❌ This voucher has expired."
            ]);
        }
        
        // Simple REDEEMABLE voucher - proceed with redemption
        Log::info("🎯 Attempting redemption", ['code' => $voucherCode, 'from' => $from]);
        
        try {
            // Create request for RedeemViaSms
            $actionRequest = ActionRequest::create('', 'POST', [
                'voucher_code' => $voucherCode,
                'mobile' => $from,
                'bank_spec' => null, // Use default/GCash
            ]);
            
            $result = (new RedeemViaSms())->asController($actionRequest);
            
            if ($result->status() === 200) {
                $data = json_decode($result->getContent(), true);
                return response()->json([
                    'message' => $data['message'] ?? "✅ Voucher redeemed successfully!"
                ]);
            } else {
                $data = json_decode($result->getContent(), true);
                return response()->json([
                    'message' => $data['message'] ?? "⚠️ Failed to redeem voucher. Please try again."
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Redemption failed", [
                'code' => $voucherCode,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => "⚠️ Failed to redeem voucher. Please try again or contact support."
            ]);
        }
    },
    [
        LogSMS::class,        // 📥 raw audit
        RateLimitSMS::class,  // ⛔ spam guard
        CleanSMS::class,      // 🧹 normalize
        AutoReplySMS::class,  // 🤖 brain
        StoreSMS::class,      // 💾 persist final
        LogSMS::class,        // 📋 post-save log
    ]
);
