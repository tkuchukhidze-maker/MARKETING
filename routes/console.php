<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CMO reporting: every morning pull the last few days from every platform
Schedule::command('reports:sync')
    ->dailyAt(config('reporting.sync_at', '06:30'))
    ->timezone('Asia/Tbilisi')
    ->withoutOverlapping();
