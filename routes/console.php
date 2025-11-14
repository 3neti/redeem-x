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
