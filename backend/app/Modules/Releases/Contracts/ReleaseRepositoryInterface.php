<?php

namespace App\Modules\Releases\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReleaseRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $organizationId, array $attributes): object;

    public function find(int $id): ?object;

    public function findForOrganization(int $organizationId, int $id): ?object;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<object>
     */
    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): object;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function updateState(int $id, string $state, array $extra = []): void;

    public function addPullRequest(int $releaseId, int $pullRequestId, ?int $addedByUserId): object;

    public function removePullRequest(int $releaseId, int $pullRequestId): void;

    public function pullRequestsForRelease(int $releaseId): Collection;

    public function repositoriesForRelease(int $releaseId): Collection;

    public function findMergedPullRequestForOrganization(int $organizationId, int $pullRequestId): ?object;

    public function findPullRequestInRelease(int $releaseId, int $pullRequestId): ?object;
}
