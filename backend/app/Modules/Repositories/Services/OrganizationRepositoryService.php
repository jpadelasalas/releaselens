<?php

namespace App\Modules\Repositories\Services;

use App\Models\User;
use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Modules\Repositories\Exceptions\RepositoryRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class OrganizationRepositoryService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $repositories,
        private readonly GitHubConnectionRepositoryInterface $connections,
        private readonly GitHubAppClientInterface $github,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $organizationId): array
    {
        return $this->repositories
            ->listForOrganization($organizationId)
            ->map(fn (object $repository): array => [
                'id' => (int) $repository->id,
                'github_repository_id' => (int) $repository->github_repository_id,
                'name' => $repository->name,
                'full_name' => $repository->full_name,
                'description' => $repository->description,
                'visibility' => $repository->visibility,
                'default_branch' => $repository->default_branch,
                'html_url' => $repository->html_url,
                'is_archived' => (bool) $repository->is_archived,
                'is_accessible' => (bool) $repository->is_accessible,
                'access_error' => $repository->access_error,
                'sync_enabled' => (bool) $repository->sync_enabled,
                'sync_status' => $repository->sync_status,
                'last_sync_at' => $repository->last_sync_at,
                'last_successful_sync_at' => $repository->last_successful_sync_at,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function available(User $user, int $organizationId): array
    {
        Gate::forUser($user)->authorize(
            OrganizationPolicy::MANAGE_REPOSITORIES,
            $organizationId,
        );
        $connection = $this->activeConnection($organizationId);
        $monitoredIds = $this->repositories->monitoredGitHubIds(
            $organizationId,
            (int) $connection->id,
        );
        $repositories = array_map(
            fn (array $repository): array => [
                ...$this->normalizeRemoteRepository($repository),
                'is_monitored' => in_array(
                    (int) ($repository['id'] ?? 0),
                    $monitoredIds,
                    true,
                ),
            ],
            $this->github->installationRepositories(
                (int) $connection->github_installation_id,
            ),
        );

        usort(
            $repositories,
            fn (array $left, array $right): int => strcasecmp(
                $left['full_name'],
                $right['full_name'],
            ),
        );

        return $repositories;
    }

    /** @param array<int, int> $repositoryIds */
    public function import(
        User $user,
        int $organizationId,
        array $repositoryIds,
    ): array {
        Gate::forUser($user)->authorize(
            OrganizationPolicy::MANAGE_REPOSITORIES,
            $organizationId,
        );
        $connection = $this->activeConnection($organizationId);
        $available = collect(
            $this->github->installationRepositories(
                (int) $connection->github_installation_id,
            ),
        )->keyBy(fn (array $repository): int => (int) ($repository['id'] ?? 0));
        $selected = [];

        foreach ($repositoryIds as $repositoryId) {
            $repository = $available->get($repositoryId);

            if (! is_array($repository)) {
                throw new RepositoryRuleException(
                    'REPOSITORY_NOT_AVAILABLE',
                    'One or more selected repositories are no longer available to the GitHub installation.',
                    422,
                );
            }

            $selected[] = $this->normalizeRemoteRepository($repository);
        }

        $this->repositories->replaceMonitoredSelection(
            $organizationId,
            (int) $connection->id,
            $selected,
        );

        return $this->list($organizationId);
    }

    /** @return array<string, mixed> */
    public function changeMonitoring(
        User $user,
        int $organizationId,
        int $repositoryId,
        bool $enabled,
    ): array {
        Gate::forUser($user)->authorize(
            OrganizationPolicy::MANAGE_REPOSITORIES,
            $organizationId,
        );
        $repository = $this->repositories->updateMonitoring(
            $organizationId,
            $repositoryId,
            $enabled,
        );

        if ($repository === null) {
            throw (new ModelNotFoundException)->setModel(
                'Repository',
                [$repositoryId],
            );
        }

        return collect($this->list($organizationId))
            ->firstWhere('id', $repositoryId);
    }

    private function activeConnection(int $organizationId): object
    {
        $connection = $this->connections
            ->activeForOrganization($organizationId);

        if ($connection === null || $connection->suspended_at !== null) {
            throw new RepositoryRuleException(
                'GITHUB_CONNECTION_REQUIRED',
                'An active GitHub connection is required to manage repositories.',
                409,
            );
        }

        return $connection;
    }

    /** @return array<string, mixed> */
    private function normalizeRemoteRepository(array $repository): array
    {
        $id = (int) ($repository['id'] ?? 0);
        $name = trim((string) ($repository['name'] ?? ''));
        $fullName = trim((string) ($repository['full_name'] ?? ''));

        if ($id <= 0 || $name === '' || $fullName === '') {
            throw new RepositoryRuleException(
                'GITHUB_REPOSITORY_INVALID',
                'GitHub returned incomplete repository information.',
                502,
            );
        }

        return [
            'github_repository_id' => $id,
            'name' => $name,
            'full_name' => $fullName,
            'description' => $repository['description'] ?? null,
            'visibility' => $repository['visibility']
                ?? (($repository['private'] ?? false) ? 'private' : 'public'),
            'default_branch' => $repository['default_branch'] ?? null,
            'html_url' => $repository['html_url'] ?? null,
            'is_archived' => (bool) ($repository['archived'] ?? false),
        ];
    }
}
