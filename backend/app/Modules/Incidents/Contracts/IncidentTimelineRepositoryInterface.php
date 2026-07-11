<?php

namespace App\Modules\Incidents\Contracts;

use Illuminate\Support\Collection;

interface IncidentTimelineRepositoryInterface
{
    public function record(int $incidentId, ?int $actorUserId, string $entryType, string $message): object;

    public function forIncident(int $incidentId): Collection;
}
