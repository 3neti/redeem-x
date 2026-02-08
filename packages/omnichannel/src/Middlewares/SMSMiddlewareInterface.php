<?php

namespace LBHurtado\OmniChannel\Middlewares;

interface SMSMiddlewareInterface
{
    /**
     * Handle an incoming SMS.
     *
     * @return mixed
     */
    public function handle(string $message, string $from, string $to, \Closure $next);
}
