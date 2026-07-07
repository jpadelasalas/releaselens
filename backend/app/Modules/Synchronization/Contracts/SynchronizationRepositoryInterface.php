<?php

namespace App\Modules\Synchronization\Contracts;

use Illuminate\Support\Collection;

interface SynchronizationRepositoryInterface
{
    public function repositoryForOrganization(int $organizationId, int $repositoryId): ?object;

    public function repositoryByGitHubId(int $githubRepositoryId): ?object;

    /**
     * Idempotent single-record upsert used by webhook handlers. Applies
     * the incoming payload only if it is not older than the currently
     * stored record, so out-of-order deliveries converge safely.
     *
     * @param  array<string, mixed>  $pullRequestPayload
     */
    public function upsertPullRequestFromWebhook(int $repositoryId, array $pullRequestPayload): ?object;

    /**
     * @param  array<string, mixed>  $reviewPayload
     */
    public function upsertReviewFromWebhook(int $pullRequestId, array $reviewPayload): void;

    /** @return array{run: object, created: bool} */
    public function createOrGetActiveRun(
        int $organizationId,
        int $repositoryId,
        ?int $actorUserId,
        string $triggerType = 'manual',
    ): array;

    /** @return Collection<int, object> */
    public function scheduledCandidates(): Collection;

    public function contextForRun(int $runId): ?object;

    public function markRunning(int $runId): void;

    /** @param array<string, mixed> $result */
    public function complete(int $runId, array $result): void;

    public function fail(
        int $runId,
        string $category,
        string $summary,
        string $status = 'failed',
    ): void;

    /** @return Collection<int, object> */
    public function recentRuns(int $organizationId, int $repositoryId, int $limit): Collection;
}
