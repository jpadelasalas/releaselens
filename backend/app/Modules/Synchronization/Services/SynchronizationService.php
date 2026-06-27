<?php

namespace App\Modules\Synchronization\Services;

use App\Models\User;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Repositories\Exceptions\RepositoryRuleException;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Synchronization\Jobs\SynchronizeRepositoryJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SynchronizationService
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization,
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
    ) {}

    /** @return array<string, mixed> */
    public function request(User $user, int $organizationId, int $repositoryId): array
    {
        $this->assertMemberRole($user, $organizationId, true);
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
        $this->assertMemberRole($user, $organizationId, false);

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
            'rate_limit_remaining' => $run->rate_limit_remaining === null
                ? null
                : (int) $run->rate_limit_remaining,
            'rate_limit_reset_at' => $run->rate_limit_reset_at,
            'error_category' => $run->error_category,
            'error_summary' => $run->error_summary,
        ];
    }

    private function assertMemberRole(User $user, int $organizationId, bool $managerRequired): void
    {
        $membership = $this->organizations->membershipForUser($organizationId, $user->id);

        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel('Organization', [$organizationId]);
        }

        if ($managerRequired && ! in_array($membership->role, [
            OrganizationRole::Owner->value,
            OrganizationRole::Manager->value,
        ], true)) {
            throw new AuthorizationException(
                'Only workspace Owners and Managers can request synchronization.',
            );
        }
    }
}
