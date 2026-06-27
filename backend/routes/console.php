<?php

use App\Modules\Synchronization\Services\SynchronizationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('releaselens:sync-scheduled', function (
    SynchronizationService $synchronization,
): void {
    $count = $synchronization->scheduleEnabledRepositories();
    $this->info("Queued {$count} repository synchronization run(s).");
})->purpose('Queue synchronization for enabled repositories');

Schedule::command('releaselens:sync-scheduled')
    ->everySixHours()
    ->withoutOverlapping();
