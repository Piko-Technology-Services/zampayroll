<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ZamPayroll subscription billing — run once a day (needs the Laravel scheduler cron).
\Illuminate\Support\Facades\Schedule::command('billing:run')->dailyAt('06:30')->withoutOverlapping();
