<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. They run only while the scheduler is running:
|   development:  php artisan schedule:work
|   production:   a cron job / Windows Task Scheduler entry that runs
|                 "php artisan schedule:run" every minute (see README).
*/

// Low-stock alerts are also sent instantly after every stock movement;
// this daily scan is a safety net (e.g. after re-enabling alerts in Settings).
Schedule::command('ims:check-low-stock')->dailyAt('07:00')->withoutOverlapping();
