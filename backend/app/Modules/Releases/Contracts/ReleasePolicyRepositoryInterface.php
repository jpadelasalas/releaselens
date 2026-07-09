<?php

namespace App\Modules\Releases\Contracts;

interface ReleasePolicyRepositoryInterface
{
    public function getForOrganization(int $organizationId): ?object;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForOrganization(int $organizationId, array $attributes): object;
}
