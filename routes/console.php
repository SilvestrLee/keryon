<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// K-DOMAIN-001E §20/§22 — the scheduler runs frequently and dispatches only
// due records (bounded batches); the domain-level cadence (12h health,
// TLS-polling until Ready/Failed) comes from each command's own due-query,
// not from how often the scheduler fires. withoutOverlapping()/onOneServer()
// guard the dispatch command itself; ShouldBeUnique on each job additionally
// prevents two simultaneous cycles for the same domain.
Schedule::command('domains:dispatch-health-checks')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('domains:dispatch-tls-polls')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
