<?php

use LBHurtado\OmniChannel\Middlewares\{AutoReplySMS, CleanSMS, LogSMS, RateLimitSMS, StoreSMS};
use LBHurtado\OmniChannel\Handlers\{SMSAutoRegister, SMSBalance, SMSHelp, SMSRegister};
use App\SMS\Handlers\{SMSGenerate, SMSPayable, SMSRedeem, SMSSettlement};
use LBHurtado\OmniChannel\Services\SMSRouterService;

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

// Catchall: Handle bare voucher codes (e.g., "ABC123" → redeem)
$router->register(
    '{message}',
    SMSRedeem::class,
    [
        LogSMS::class,        // 📥 raw audit
        RateLimitSMS::class,  // ⛔ spam guard
        CleanSMS::class,      // 🧹 normalize
        AutoReplySMS::class,  // 🤖 brain
        StoreSMS::class,      // 💾 persist final
        LogSMS::class,        // 📋 post-save log
    ]
);
