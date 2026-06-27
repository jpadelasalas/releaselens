<?php

namespace App\Modules\GitHub\Services;

use App\Models\User;
use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GitHubConnectionService
{
    private const STATE_SESSION_KEY = 'releaselens.github_connection_state';

    public function __construct(
        private readonly GitHubConnectionRepositoryInterface $connections,
        private readonly GitHubAppClientInterface $github,
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
    ) {}

    /** @return array{url: string} */
    public function begin(User $user, int $organizationId, Request $request): array
    {
        $this->assertRole($user, $organizationId, [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ]);
        $appSlug = trim((string) config('releaselens.github.app_slug'));

        if ($appSlug === '') {
            throw new GitHubConnectionException(
                'GITHUB_APP_NOT_CONFIGURED',
                'GitHub connection is not configured for this environment.',
                503,
            );
        }

        if ($this->connections->activeForOrganization($organizationId) !== null) {
            throw new GitHubConnectionException(
                'GITHUB_ALREADY_CONNECTED',
                'This workspace already has an active GitHub connection.',
                409,
            );
        }

        $state = Str::random(64);
        $request->session()->put(self::STATE_SESSION_KEY, [
            'hash' => hash('sha256', $state),
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'expires_at' => now()
                ->addMinutes((int) config('releaselens.github.state_ttl_minutes'))
                ->timestamp,
        ]);

        return [
            'url' => 'https://github.com/apps/'.rawurlencode($appSlug)
                .'/installations/new?state='.rawurlencode($state),
        ];
    }

    public function complete(
        User $user,
        string $state,
        int $installationId,
        Request $request,
    ): object {
        $pending = $request->session()->pull(self::STATE_SESSION_KEY);

        if (! is_array($pending) ||
            ! isset($pending['hash'], $pending['organization_id'], $pending['user_id'], $pending['expires_at']) ||
            ! hash_equals((string) $pending['hash'], hash('sha256', $state)) ||
            (int) $pending['user_id'] !== (int) $user->id ||
            (int) $pending['expires_at'] < now()->timestamp) {
            throw new GitHubConnectionException(
                'GITHUB_STATE_INVALID',
                'The GitHub connection request expired or could not be verified.',
                419,
            );
        }

        $organizationId = (int) $pending['organization_id'];
        $this->assertRole($user, $organizationId, [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ]);
        $existingForOrganization = $this->connections
            ->activeForOrganization($organizationId);

        if ($existingForOrganization !== null &&
            (int) $existingForOrganization->github_installation_id !== $installationId) {
            throw new GitHubConnectionException(
                'GITHUB_ALREADY_CONNECTED',
                'This workspace already has an active GitHub connection.',
                409,
            );
        }

        $existingInstallation = $this->connections
            ->activeByInstallationId($installationId);

        if ($existingInstallation !== null &&
            (int) $existingInstallation->organization_id !== $organizationId) {
            throw new GitHubConnectionException(
                'GITHUB_INSTALLATION_IN_USE',
                'That GitHub installation is already linked to another workspace.',
                409,
            );
        }

        $metadata = $this->github->installation($installationId);

        if ((int) ($metadata['id'] ?? 0) !== $installationId) {
            throw new GitHubConnectionException(
                'GITHUB_INSTALLATION_INVALID',
                'GitHub returned unexpected installation information.',
                502,
            );
        }

        $this->assertReadOnlyPermissions($metadata['permissions'] ?? []);

        $connection = $this->connections->connect(
            $organizationId,
            $installationId,
            $metadata,
        );
        $this->connections->recordAuditEvent(
            $organizationId,
            $user->id,
            'github.connected',
            $connection->id,
            ['github_account_login' => $connection->github_account_login],
            $request->ip(),
            $request->userAgent(),
        );

        return $connection;
    }

    /** @return array<string, mixed>|null */
    public function status(User $user, int $organizationId): ?array
    {
        $this->assertRole($user, $organizationId, [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
            OrganizationRole::Viewer,
        ]);
        $connection = $this->connections->activeForOrganization($organizationId);

        return $connection === null ? null : $this->payload($connection);
    }

    public function disconnect(User $user, int $organizationId, Request $request): void
    {
        $this->assertRole($user, $organizationId, [OrganizationRole::Owner]);
        $connection = $this->connections->activeForOrganization($organizationId);

        if ($connection === null) {
            throw (new ModelNotFoundException)->setModel('GitHubInstallation');
        }

        $this->connections->disconnect(
            $organizationId,
            $connection->id,
            $user->id,
            $request->ip(),
            $request->userAgent(),
        );
    }

    /** @return array<string, mixed> */
    private function payload(object $connection): array
    {
        return [
            'status' => $connection->suspended_at === null ? 'active' : 'action_required',
            'account' => [
                'login' => $connection->github_account_login,
                'type' => $connection->github_account_type,
            ],
            'repository_selection' => $connection->repository_selection,
            'permissions' => json_decode($connection->permissions ?? '{}', true),
            'connected_at' => $connection->connected_at,
            'suspended_at' => $connection->suspended_at,
        ];
    }

    /** @param array<string, mixed> $permissions */
    private function assertReadOnlyPermissions(array $permissions): void
    {
        $hasWritePermission = false;

        foreach ($permissions as $level) {
            if (in_array($level, ['write', 'admin'], true)) {
                $hasWritePermission = true;
                break;
            }
        }

        if ($hasWritePermission || ($permissions['pull_requests'] ?? null) !== 'read') {
            throw new GitHubConnectionException(
                'GITHUB_PERMISSIONS_INVALID',
                'The GitHub App must use read-only pull-request permissions.',
                422,
            );
        }
    }

    /** @param array<int, OrganizationRole> $allowedRoles */
    private function assertRole(User $user, int $organizationId, array $allowedRoles): void
    {
        $membership = $this->organizations->membershipForUser(
            $organizationId,
            $user->id,
        );

        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel('Organization', [$organizationId]);
        }

        $allowedValues = array_map(
            fn (OrganizationRole $role): string => $role->value,
            $allowedRoles,
        );

        if (! in_array($membership->role, $allowedValues, true)) {
            throw new AuthorizationException(
                'Your workspace role cannot perform this GitHub connection action.',
            );
        }
    }
}
