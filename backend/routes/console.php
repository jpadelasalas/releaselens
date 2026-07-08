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

// Manual/ops-triggered reconciliation (V2-FR-REC-001, V2-FR-REC-011). Not
// scheduled automatically alongside sync-scheduled above - running both on
// the same cadence would poll GitHub twice for the same repositories with
// no benefit. See SynchronizationService::reconcileEnabledRepositories().
Artisan::command('releaselens:sync-reconcile', function (
    SynchronizationService $synchronization,
): void {
    $count = $synchronization->reconcileEnabledRepositories();
    $this->info("Queued {$count} reconciliation run(s).");
})->purpose('Queue reconciliation for enabled repositories');
