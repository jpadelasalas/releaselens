<?php

namespace App\Modules\Deployments\Repositories;

use App\Modules\Deployments\Contracts\EnvironmentMappingRepositoryInterface;
use Illuminate\Support\Collection;
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

    public function listForOrganization(int $organizationId): Collection
    {
        return DB::table('environment_mappings')
            ->where('organization_id', $organizationId)
            ->orderBy('source_environment')
            ->get();
    }

    public function upsertForOrganization(
        int $organizationId,
        string $sourceEnvironment,
        string $normalizedEnvironment,
        bool $isProduction,
    ): object {
        $now = now();
        $existing = DB::table('environment_mappings')
            ->where('organization_id', $organizationId)
            ->where('source_environment', $sourceEnvironment)
            ->first();

        if ($existing === null) {
            $id = (int) DB::table('environment_mappings')->insertGetId([
                'organization_id' => $organizationId,
                'source_environment' => $sourceEnvironment,
                'normalized_environment' => $normalizedEnvironment,
                'is_production' => $isProduction,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('environment_mappings')->find($id);
        }

        DB::table('environment_mappings')->where('id', $existing->id)->update([
            'normalized_environment' => $normalizedEnvironment,
            'is_production' => $isProduction,
            'updated_at' => $now,
        ]);

        return DB::table('environment_mappings')->find($existing->id);
    }
}
