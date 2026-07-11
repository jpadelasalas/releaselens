<?php

namespace App\Modules\Deployments\Contracts;

interface EnvironmentMappingRepositoryInterface
{
    /**
     * @return array{normalized_environment: string, is_production: bool}
     */
    public function resolve(int $organizationId, string $rawEnvironment): array;
}
