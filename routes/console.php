<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('services.movie_sync.enabled')) {
    Schedule::command('movies:sync-popular')
        ->dailyAt((string) config('services.movie_sync.daily_at', '03:00'))
        ->timezone((string) config('services.movie_sync.timezone', 'Africa/Cairo'))
        ->withoutOverlapping(360)
        ->onOneServer();
}

if (config('services.content_worker.enabled')) {
    Schedule::command('elnemr:sync-worker', [
        '--type' => 'all',
        '--pages' => (string) config('services.content_worker.pages', 2),
        '--limit' => (string) config('services.content_worker.limit', 50),
    ])->dailyAt((string) config('services.content_worker.daily_at', '04:00'))
        ->timezone((string) config('services.content_worker.timezone', 'Africa/Cairo'))
        ->withoutOverlapping(120)
        ->onOneServer();
}
