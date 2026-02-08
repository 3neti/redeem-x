<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Closure;
use Illuminate\Support\Facades\Log;

class LogSMS implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        Log::info('📩 Incoming SMS', compact('message', 'from', 'to'));

        return $next($message, $from, $to);
    }
}
