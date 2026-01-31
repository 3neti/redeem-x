<?php

use LBHurtado\OmniChannel\Middlewares\{AutoReplySMS, CleanSMS, LogSMS, RateLimitSMS, StoreSMS};
use LBHurtado\OmniChannel\Handlers\{SMSAutoRegister, SMSBalance, SMSRegister};
use App\SMS\Handlers\{SMSGenerate, SMSPayable, SMSSettlement};
use LBHurtado\OmniChannel\Services\SMSRouterService;
use Illuminate\Support\Facades\Log;

/** @var SMSRouterService $router */
$router = resolve(SMSRouterService::class);
//Log::info("✅  Resolved SMSRouterService instance.", ['instance' => get_class($router)]);

$router->register('REGISTER {mobile?} {extra?}', SMSRegister::class);
$router->register('REG {email} {extra?}', SMSAutoRegister::class);
$router->register('BALANCE {flag?}', SMSBalance::class);

// Voucher generation commands (must be registered before catchall)
$router->register('GENERATE {amount}', SMSGenerate::class);
$router->register('REDEEMABLE {amount}', SMSGenerate::class);
$router->register('PAYABLE {amount}', SMSPayable::class);
$router->register('SETTLEMENT {amount} {target}', SMSSettlement::class);

$router->register(
    '{message}',
    function ($values, $from, $to) {
        Log::info("📩 SMS Route Matched", ['message' => $values['message'], 'from' => $from, 'to' => $to]);

        return response()->json([
            'message' => null
        ]);
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
