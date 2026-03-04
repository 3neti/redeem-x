<?php

use App\SMS\Handlers\SMSGenerate;
use App\SMS\Handlers\SMSPayable;
use App\SMS\Handlers\SMSRedeem;
use App\SMS\Handlers\SMSSettlement;
use LBHurtado\OmniChannel\Handlers\SMSAutoRegister;
use LBHurtado\OmniChannel\Handlers\SMSBalance;
use LBHurtado\OmniChannel\Handlers\SMSHelp;
use LBHurtado\OmniChannel\Handlers\SMSRegister;
use LBHurtado\OmniChannel\Middlewares\AutoReplySMS;
use LBHurtado\OmniChannel\Middlewares\CleanSMS;
use LBHurtado\OmniChannel\Middlewares\LogSMS;
use LBHurtado\OmniChannel\Middlewares\RateLimitSMS;
use LBHurtado\OmniChannel\Middlewares\StoreSMS;
use LBHurtado\OmniChannel\Services\SMSRouterService;

/** @var SMSRouterService $router */
$router = resolve(SMSRouterService::class);
// Log::info("✅  Resolved SMSRouterService instance.", ['instance' => get_class($router)]);

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
