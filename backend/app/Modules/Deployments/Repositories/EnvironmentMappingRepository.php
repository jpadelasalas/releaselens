<?php

namespace App\Modules\Deployments\Repositories;

use App\Modules\Deployments\Contracts\EnvironmentMappingRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EnvironmentMappingRepository implements EnvironmentMappingRepositoryInterface
{
    public function resolve(int $organizationId, string $rawEnvironment): array
    {
        $mapping = DB::table('environment_mappings')
            ->where('organization_id', $organizationId)
            ->where('source_environment', $rawEnvironment)
            ->first();

        if ($mapping !== null) {
            return [
                'normalized_environment' => $mapping->normalized_environment,
                'is_production' => (bool) $mapping->is_production,
            ];
        }

        return [
            'normalized_environment' => mb_strtolower($rawEnvironment),
            'is_production' => false,
        ];
    }
}
