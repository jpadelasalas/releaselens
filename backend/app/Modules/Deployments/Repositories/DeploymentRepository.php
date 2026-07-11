<?php

namespace App\Modules\Deployments\Repositories;

use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeploymentRepository implements DeploymentRepositoryInterface
{
    public function findByGitHubDeploymentId(int $githubDeploymentId): ?object
    {
        return DB::table('deployments')
            ->where('github_deployment_id', $githubDeploymentId)
            ->first();
    }

    public function upsertFromWebhook(int $organizationId, int $repositoryId, array $attributes): object
    {
        $existing = $this->findByGitHubDeploymentId((int) $attributes['github_deployment_id']);

        if ($existing !== null) {
            return $existing;
        }

        $now = now();
        $id = (int) DB::table('deployments')->insertGetId([
            'organization_id' => $organizationId,
            'repository_id' => $repositoryId,
            'status' => 'pending',
            'original_status' => 'pending',
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('deployments')->find($id);
    }

    public function recordStatusEvent(int $deploymentId, array $attributes): object
    {
        $now = now();
        $id = (int) DB::table('deployment_status_events')->insertGetId([
            'deployment_id' => $deploymentId,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('deployments')->where('id', $deploymentId)->update([
            'status' => $attributes['status'],
            'original_status' => $attributes['original_status'],
            'updated_at_github' => $attributes['occurred_at'],
            'updated_at' => $now,
        ]);

        return DB::table('deployment_status_events')->find($id);
    }

    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('deployments')
            ->join('repositories', 'repositories.id', '=', 'deployments.repository_id')
            ->where('deployments.organization_id', $organizationId)
            ->select(['deployments.*', 'repositories.name as repository_name']);

        if (! empty($filters['status'])) {
            $query->where('deployments.status', $filters['status']);
        }

        if (! empty($filters['environment'])) {
            $query->where('deployments.normalized_environment', $filters['environment']);
        }

        return $query->orderByDesc('deployments.id')->paginate($perPage);
    }

    public function findForOrganization(int $organizationId, int $id): ?object
    {
        return DB::table('deployments')
            ->join('repositories', 'repositories.id', '=', 'deployments.repository_id')
            ->where('deployments.organization_id', $organizationId)
            ->where('deployments.id', $id)
            ->select(['deployments.*', 'repositories.name as repository_name'])
            ->first();
    }

    public function statusEventsForDeployment(int $deploymentId): Collection
    {
        return DB::table('deployment_status_events')
            ->where('deployment_id', $deploymentId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    public function linkRelease(int $deploymentId, ?int $releaseId): void
    {
        DB::table('deployments')->where('id', $deploymentId)->update([
            'release_id' => $releaseId,
            'updated_at' => now(),
        ]);
    }

    public function forRelease(int $releaseId): Collection
    {
        return DB::table('deployments')
            ->join('repositories', 'repositories.id', '=', 'deployments.repository_id')
            ->where('deployments.release_id', $releaseId)
            ->orderByDesc('deployments.created_at_github')
            ->select(['deployments.*', 'repositories.name as repository_name'])
            ->get();
    }
}
