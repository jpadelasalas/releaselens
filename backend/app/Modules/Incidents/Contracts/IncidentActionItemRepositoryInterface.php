<?php

namespace App\Modules\Incidents\Contracts;

use Illuminate\Support\Collection;

interface IncidentActionItemRepositoryInterface
{
    public function add(int $incidentId, string $description, ?int $assignedToUserId): object;

    public function find(int $incidentId, int $id): ?object;

    public function complete(int $id, int $completedByUserId): object;

    public function uncomplete(int $id): object;

    public function remove(int $incidentId, int $id): void;

    public function forIncident(int $incidentId): Collection;
}
