<?php

namespace LBHurtado\OmniChannel\Middlewares;

use Closure;
use LBHurtado\OmniChannel\Models\SMS;

class StoreSMS implements SMSMiddlewareInterface
{
    public function handle(string $message, string $from, string $to, Closure $next)
    {
        SMS::create([
            'from' => $from,
            'to' => $to,
            'message' => $message,
        ]);

        return $next($message, $from, $to);
    }
}
