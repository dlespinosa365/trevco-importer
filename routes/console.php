<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('flows:schedule-runner')
    ->everyMinute()
    ->withoutOverlapping();

if (config('flows.reconcile_stale_running_enabled', true)) {
    Schedule::command(sprintf(
        'flows:reconcile-stale-running --minutes=%d',
        (int) config('flows.reconcile_stale_running_minutes', 180),
    ))
        ->hourly()
        ->withoutOverlapping();
}

Schedule::command('horizon:snapshot')->everyFiveMinutes();
