<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule balance checks to run every hour
Schedule::command('balances:check --all')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Reconcile pending disbursements every 15 minutes
Schedule::command('disbursement:reconcile-pending')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
