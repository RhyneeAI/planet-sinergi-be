<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean old reports every day at 2 AM
Schedule::command('reports:clean --days=1')->dailyAt('02:00');
Schedule::command('telescope:prune --hours=48')->daily();

// Queue worker for async exports (Redis) — runs every minute
Schedule::command('queue:work redis --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();