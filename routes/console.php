<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rentals:check-late')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/late-rentals.log'));

// Time configurable at Settings → Admin App & Push (key `daily_reminder_time`).
$dailyReminderTime = '17:00';
try {
    $configured = \App\Models\Setting::get('daily_reminder_time', '17:00');
    if (is_string($configured) && preg_match('/^(\d{1,2}):(\d{2})/', $configured, $m)) {
        $dailyReminderTime = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
} catch (\Throwable $e) {
    // DB not ready yet (e.g. before install) — fall back to default.
}

Schedule::command('app:send-rental-reminders')
    ->dailyAt($dailyReminderTime)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rental-reminders.log'));

Schedule::command('finance:run-depreciation')
    ->lastDayOfMonth('23:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/depreciation.log'));

Schedule::command('computer-bookings:process')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/computer-bookings.log'));

Schedule::command('maintenance:flag-due')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/maintenance-due.log'));
