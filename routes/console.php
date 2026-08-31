<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Reminders for tomorrow's appointments.
 *
 * Hourly rather than once a day: the command only picks up appointments falling
 * inside a one hour window, and sending is idempotent, so running often costs
 * nothing and means a missed run is caught up rather than lost.
 *
 * Locally this needs `php artisan schedule:work` running. In production it is
 * the usual single cron entry calling `schedule:run` every minute.
 */
Schedule::command('appointments:remind')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
