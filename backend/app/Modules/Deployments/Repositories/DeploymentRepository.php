<?php

namespace App\Modules\Deployments\Repositories;

use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
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
}
