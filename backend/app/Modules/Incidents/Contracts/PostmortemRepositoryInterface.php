<?php

namespace App\Modules\Incidents\Contracts;

interface PostmortemRepositoryInterface
{
    public function find(int $incidentId): ?object;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(int $incidentId, array $attributes): object;

    public function publish(int $incidentId): object;
}
