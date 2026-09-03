<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sellerscope:sync-due')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('sellerscope:recover-stalled-syncs')
    ->everyTenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('sellerscope:prune-raw-imports')
    ->dailyAt('03:15')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('queue:monitor redis:sync,redis:analytics,redis:notifications,redis:maintenance --max='
    .(int) config('sellerscope.synchronization.queue_backlog_alert', 250))
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->onOneServer();
