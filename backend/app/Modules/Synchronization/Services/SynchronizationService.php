<?php

namespace App\Modules\Synchronization\Services;

use App\Models\User;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Repositories\Exceptions\RepositoryRuleException;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Synchronization\Jobs\SynchronizeRepositoryJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class SynchronizationService
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization,
    ) {}

    /** @return array<string, mixed> */
    public function request(User $user, int $organizationId, int $repositoryId): array
    {
        Gate::forUser($user)->authorize(
            OrganizationPolicy::REQUEST_SYNCHRONIZATION,
            $organizationId,
        );
        $repository = $this->synchronization->repositoryForOrganization(
            $organizationId,
            $repositoryId,
        );

        if ($repository === null) {
            throw (new ModelNotFoundException)->setModel('Repository', [$repositoryId]);
        }

        if (! $repository->sync_enabled) {
            throw new RepositoryRuleException(
                'REPOSITORY_MONITORING_DISABLED',
                'Enable repository monitoring before requesting synchronization.',
                409,
            );
        }

        if ($repository->external_installation_id === null ||
            $repository->installation_suspended_at !== null ||
            $repository->installation_disconnected_at !== null) {
            throw new RepositoryRuleException(
                'GITHUB_CONNECTION_REQUIRED',
                'An active GitHub connection is required to synchronize this repository.',
                409,
            );
        }

        $result = $this->synchronization->createOrGetActiveRun(
            $organizationId,
            $repositoryId,
            $user->id,
        );

        if ($result['created']) {
            SynchronizeRepositoryJob::dispatch((int) $result['run']->id)
                ->afterCommit();
        }

        return $this->payload($result['run']);
    }

    /** @return array<int, array<string, mixed>> */
    public function history(User $user, int $organizationId, int $repositoryId): array
    {
        Gate::forUser($user)->authorize(
            OrganizationPolicy::VIEW,
            $organizationId,
        );

        if ($this->synchronization->repositoryForOrganization($organizationId, $repositoryId) === null) {
            throw (new ModelNotFoundException)->setModel('Repository', [$repositoryId]);
        }

        return $this->synchronization
            ->recentRuns($organizationId, $repositoryId, 20)
            ->map(fn (object $run): array => $this->payload($run))
            ->all();
    }

    public function scheduleEnabledRepositories(): int
    {
        $scheduled = 0;

        foreach ($this->synchronization->scheduledCandidates() as $repository) {
            $result = $this->synchronization->createOrGetActiveRun(
                (int) $repository->organization_id,
                (int) $repository->repository_id,
                null,
                'scheduled',
            );

            if ($result['created']) {
                SynchronizeRepositoryJob::dispatch((int) $result['run']->id)
                    ->afterCommit();
                $scheduled++;
            }
        }

        return $scheduled;
    }

    /**
     * Manually triggered reconciliation for every enabled repository, using
     * the same idempotent sync engine as scheduleEnabledRepositories() but
     * tagged with a distinct trigger_type for ops visibility (V2-FR-REC-001,
     * V2-FR-REC-011). Not wired into the live schedule alongside the
     * existing six-hour poll - running both on the same cadence would poll
     * GitHub twice for no benefit. Whether/how to fold this into scheduled
     * polling per ADR2-003 is an operational decision, not made here.
     */
    public function reconcileEnabledRepositories(): int
    {
        $reconciled = 0;

        foreach ($this->synchronization->scheduledCandidates() as $repository) {
            $result = $this->synchronization->createOrGetActiveRun(
                (int) $repository->organization_id,
                (int) $repository->repository_id,
                null,
                'reconciliation',
            );

            if ($result['created']) {
                SynchronizeRepositoryJob::dispatch((int) $result['run']->id)
                    ->afterCommit();
                $reconciled++;
            }
        }

        return $reconciled;
    }

    /** @return array<string, mixed> */
    private function payload(object $run): array
    {
        return [
            'id' => (int) $run->id,
            'repository_id' => (int) $run->repository_id,
            'trigger_type' => $run->trigger_type,
            'status' => $run->status,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'created_count' => (int) $run->created_count,
            'updated_count' => (int) $run->updated_count,
            'unchanged_count' => (int) $run->unchanged_count,
            'failed_count' => (int) $run->failed_count,
            'inaccessible_count' => (int) $run->inaccessible_count,
            'unsupported_count' => (int) $run->unsupported_count,
            'rate_limit_remaining' => $run->rate_limit_remaining === null
                ? null
                : (int) $run->rate_limit_remaining,
            'rate_limit_reset_at' => $run->rate_limit_reset_at,
            'error_category' => $run->error_category,
            'error_summary' => $run->error_summary,
        ];
    }
}
