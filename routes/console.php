<?php

use App\Console\Commands\SendDailyStatistics;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendDailyStatistics::class)
    ->everyMinute()
    ->name('daily-site-statistics');