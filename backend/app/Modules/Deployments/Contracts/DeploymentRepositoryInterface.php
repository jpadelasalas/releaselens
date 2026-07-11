<?php

namespace App\Modules\Deployments\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<object>
     */
    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator;

    public function findForOrganization(int $organizationId, int $id): ?object;

    public function statusEventsForDeployment(int $deploymentId): Collection;

    public function linkRelease(int $deploymentId, ?int $releaseId): void;

    public function forRelease(int $releaseId): Collection;
}
