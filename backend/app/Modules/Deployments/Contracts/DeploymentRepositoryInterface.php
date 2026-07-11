<?php

namespace App\Modules\Deployments\Contracts;

interface DeploymentRepositoryInterface
{
    public function findByGitHubDeploymentId(int $githubDeploymentId): ?object;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertFromWebhook(int $organizationId, int $repositoryId, array $attributes): object;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordStatusEvent(int $deploymentId, array $attributes): object;
}
