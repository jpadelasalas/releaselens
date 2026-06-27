<?php

namespace App\Modules\Synchronization\Contracts;

use Illuminate\Support\Collection;

interface SynchronizationRepositoryInterface
{
    public function repositoryForOrganization(int $organizationId, int $repositoryId): ?object;

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
