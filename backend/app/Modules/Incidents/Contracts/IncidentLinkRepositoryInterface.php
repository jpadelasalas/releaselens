<?php

namespace App\Modules\Incidents\Contracts;

use Illuminate\Support\Collection;

interface IncidentLinkRepositoryInterface
{
    public function link(int $incidentId, string $linkableType, int $linkableId): object;

    public function find(int $incidentId, int $id): ?object;

    public function remove(int $incidentId, int $id): void;

    public function forIncident(int $incidentId): Collection;
}
