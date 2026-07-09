<?php

namespace App\Modules\Releases\Contracts;

interface ReleaseRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $organizationId, array $attributes): object;

    public function find(int $id): ?object;

    public function findForOrganization(int $organizationId, int $id): ?object;
}
