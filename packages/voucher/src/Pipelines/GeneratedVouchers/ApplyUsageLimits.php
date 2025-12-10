<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Illuminate\Support\Facades\Log;
use Closure;

class ApplyUsageLimits
{
    private const DEBUG = false;
    
    public function handle($vouchers, Closure $next)
    {
        // @todo Implement global or per-user voucher issuance caps
        if (self::DEBUG) {
            Log::info('Applying usage limits to generated vouchers.');
        }

        // Example: reject if voucher creator exceeded quota (to be implemented)

        return $next($vouchers);
    }
}
