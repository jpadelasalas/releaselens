<?php

namespace App\Modules\Incidents\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface IncidentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $organizationId, array $attributes): object;

    public function find(int $id): ?object;

    public function findForOrganization(int $organizationId, int $id): ?object;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<object>
     */
    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): object;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function updateState(int $id, string $state, array $extra = []): void;
}
