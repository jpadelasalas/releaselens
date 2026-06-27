<?php

namespace App\Modules\Repositories\Repositories;

use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function listForOrganization(int $organizationId): Collection
    {
        return DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get([
                'id',
                'github_repository_id',
                'name',
                'full_name',
                'description',
                'visibility',
                'default_branch',
                'html_url',
                'is_archived',
                'is_accessible',
                'access_error',
                'sync_enabled',
                'sync_status',
                'last_sync_at',
                'last_successful_sync_at',
            ]);
    }

    public function monitoredGitHubIds(
        int $organizationId,
        int $installationRecordId,
    ): array {
        return DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->where('github_installation_id', $installationRecordId)
            ->where('sync_enabled', true)
            ->pluck('github_repository_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function replaceMonitoredSelection(
        int $organizationId,
        int $installationRecordId,
        array $repositories,
    ): void {
        DB::transaction(function () use ($organizationId, $installationRecordId, $repositories): void {
            $now = now();
            DB::table('repositories')
                ->where('organization_id', $organizationId)
                ->where('github_installation_id', $installationRecordId)
                ->update(['sync_enabled' => false, 'updated_at' => $now]);

            foreach ($repositories as $repository) {
                $identity = [
                    'organization_id' => $organizationId,
                    'github_repository_id' => $repository['github_repository_id'],
                ];
                $values = [
                    'github_installation_id' => $installationRecordId,
                    'name' => $repository['name'],
                    'full_name' => $repository['full_name'],
                    'description' => $repository['description'],
                    'visibility' => $repository['visibility'],
                    'default_branch' => $repository['default_branch'],
                    'html_url' => $repository['html_url'],
                    'is_archived' => $repository['is_archived'],
                    'is_accessible' => true,
                    'access_error' => null,
                    'sync_enabled' => true,
                    'updated_at' => $now,
                ];
                $query = DB::table('repositories')->where($identity);

                if ($query->exists()) {
                    $query->update($values);
                } else {
                    DB::table('repositories')->insert([
                        ...$identity,
                        ...$values,
                        'created_at' => $now,
                    ]);
                }
            }
        });
    }

    public function updateMonitoring(
        int $organizationId,
        int $repositoryId,
        bool $enabled,
    ): ?object {
        $updated = DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->where('id', $repositoryId)
            ->update(['sync_enabled' => $enabled, 'updated_at' => now()]);

        if ($updated === 0 && ! DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->where('id', $repositoryId)
            ->exists()) {
            return null;
        }

        return DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->where('id', $repositoryId)
            ->first();
    }
}
