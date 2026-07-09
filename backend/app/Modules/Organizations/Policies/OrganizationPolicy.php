<?php

namespace App\Modules\Organizations\Policies;

use App\Models\User;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
{
    public const VIEW = 'organization.view';

    public const MANAGE_MEMBERS = 'organization.manage-members';

    public const MANAGE_GITHUB = 'organization.manage-github';

    public const DISCONNECT_GITHUB = 'organization.disconnect-github';

    public const MANAGE_REPOSITORIES = 'organization.manage-repositories';

    public const REQUEST_SYNCHRONIZATION = 'organization.request-synchronization';

    public const MANAGE_RELEASES = 'organization.manage-releases';

    public function __construct(
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
    ) {}

    public function view(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'You do not have access to this workspace.',
            OrganizationRole::Owner,
            OrganizationRole::Manager,
            OrganizationRole::Viewer,
        );
    }

    public function manageMembers(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners can manage members.',
            OrganizationRole::Owner,
        );
    }

    public function manageGitHub(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners and Managers can manage the GitHub connection.',
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        );
    }

    public function disconnectGitHub(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners can disconnect GitHub.',
            OrganizationRole::Owner,
        );
    }

    public function manageRepositories(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners and Managers can manage repositories.',
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        );
    }

    public function requestSynchronization(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners and Managers can request synchronization.',
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        );
    }

    public function manageReleases(User $user, int $organizationId): Response
    {
        return $this->authorizeRoles(
            $user,
            $organizationId,
            'Only workspace Owners and Managers can manage releases.',
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        );
    }

    private function authorizeRoles(
        User $user,
        int $organizationId,
        string $denialMessage,
        OrganizationRole ...$roles,
    ): Response {
        $membership = $this->organizations->membershipForUser(
            $organizationId,
            $user->id,
        );

        if ($membership === null) {
            return Response::denyAsNotFound();
        }

        $allowedRoles = array_map(
            fn (OrganizationRole $role): string => $role->value,
            $roles,
        );

        return in_array($membership->role, $allowedRoles, true)
            ? Response::allow()
            : Response::deny($denialMessage);
    }
}
