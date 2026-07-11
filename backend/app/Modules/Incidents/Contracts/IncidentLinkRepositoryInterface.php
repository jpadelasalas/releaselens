<?php

namespace App\Modules\Incidents\Contracts;

use Illuminate\Support\Collection;

interface IncidentLinkRepositoryInterface
{
    public function link(int $incidentId, string $linkableType, int $linkableId): object;

    public function unlink(int $incidentId, string $linkableType, int $linkableId): void;

    public function forIncident(int $incidentId): Collection;
}
