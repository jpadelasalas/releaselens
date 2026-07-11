<?php

namespace App\Modules\Deployments\Contracts;

use Illuminate\Support\Collection;

interface EnvironmentMappingRepositoryInterface
{
    /**
     * @return array{normalized_environment: string, is_production: bool}
     */
    public function resolve(int $organizationId, string $rawEnvironment): array;

    public function listForOrganization(int $organizationId): Collection;

    public function upsertForOrganization(
        int $organizationId,
        string $sourceEnvironment,
        string $normalizedEnvironment,
        bool $isProduction,
    ): object;
}
