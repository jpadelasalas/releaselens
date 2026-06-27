<?php

namespace App\Modules\Organizations\Services;

use App\Models\User;
use App\Modules\Identity\Contracts\UserRepositoryInterface;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Exceptions\OrganizationRuleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationWorkspaceService
{
    public function __construct(
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function listForUser(User $user): array
    {
        return $this->organizations->membershipsForUser($user->id)
            ->map(fn (object $membership): array => $this->membershipPayload($membership))
            ->all();
    }

    public function create(
        User $user,
        array $attributes,
        Request $request,
    ): object {
        $organization = $this->organizations->createWithOwner(
            $user->id,
            $attributes['name'],
            $this->uniqueSlug($attributes['name']),
            $attributes['timezone'],
        );

        $request->session()->put(
            'releaselens.active_organization_id',
            $organization->id,
        );

        return $organization;
    }

    public function activate(
        User $user,
        int $organizationId,
        Request $request,
    ): object {
        $membership = $this->organizations->membershipForUser(
            $organizationId,
            $user->id,
        );

        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel(
                'Organization',
                [$organizationId],
            );
        }

        $request->session()->put(
            'releaselens.active_organization_id',
            $organizationId,
        );

        return $membership;
    }

    /** @return array<string, mixed> */
    public function membershipPayload(object $membership): array
    {
        return [
            'organization' => [
                'id' => (int) $membership->id,
                'name' => $membership->name,
                'slug' => $membership->slug,
                'timezone' => $membership->timezone,
            ],
            'role' => $membership->role,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listMembers(User $actor, int $organizationId): array
    {
        $this->assertOwner($actor, $organizationId);

        return $this->organizations->membersForOrganization($organizationId)
            ->map(fn (object $member): array => $this->memberPayload($member))
            ->all();
    }

    /** @return array<string, mixed> */
    public function addMember(
        User $actor,
        int $organizationId,
        string $email,
        OrganizationRole $role,
        Request $request,
    ): array {
        $this->assertOwner($actor, $organizationId);
        $targetUser = $this->users->findByNormalizedEmail(
            mb_strtolower(trim($email)),
        );

        if ($targetUser === null || $targetUser->disabled_at !== null) {
            throw new OrganizationRuleException(
                'MEMBER_ACCOUNT_NOT_FOUND',
                'No active ReleaseLens account matches that email address.',
                422,
            );
        }

        if (
            $this->organizations->membershipForUser(
                $organizationId,
                $targetUser->id,
            ) !== null
        ) {
            throw new OrganizationRuleException(
                'MEMBER_ALREADY_EXISTS',
                'That user is already a workspace member.',
                409,
            );
        }

        $member = $this->organizations->addMember(
            $organizationId,
            $targetUser->id,
            $role->value,
        );
        $this->audit(
            $organizationId,
            $actor,
            'member.added',
            $member,
            ['role' => $role->value, 'target_user_id' => $targetUser->id],
            $request,
        );

        return $this->memberPayload($member);
    }

    /** @return array<string, mixed> */
    public function changeMemberRole(
        User $actor,
        int $organizationId,
        int $membershipId,
        OrganizationRole $role,
        Request $request,
    ): array {
        $this->assertOwner($actor, $organizationId);
        $member = $this->memberOrFail($organizationId, $membershipId);

        if (
            $member->role === OrganizationRole::Owner->value &&
            $role !== OrganizationRole::Owner &&
            $this->organizations->ownerCount($organizationId) <= 1
        ) {
            $this->throwLastOwnerException();
        }

        $previousRole = $member->role;
        $this->organizations->updateMemberRole($membershipId, $role->value);
        $member = $this->memberOrFail($organizationId, $membershipId);
        $this->audit(
            $organizationId,
            $actor,
            'member.role_changed',
            $member,
            ['previous_role' => $previousRole, 'role' => $role->value],
            $request,
        );

        return $this->memberPayload($member);
    }

    public function removeMember(
        User $actor,
        int $organizationId,
        int $membershipId,
        Request $request,
    ): void {
        $this->assertOwner($actor, $organizationId);
        $member = $this->memberOrFail($organizationId, $membershipId);

        if (
            $member->role === OrganizationRole::Owner->value &&
            $this->organizations->ownerCount($organizationId) <= 1
        ) {
            $this->throwLastOwnerException();
        }

        $this->organizations->removeMember($membershipId);
        $this->audit(
            $organizationId,
            $actor,
            'member.removed',
            $member,
            ['role' => $member->role, 'target_user_id' => (int) $member->user_id],
            $request,
        );

        if ((int) $member->user_id === (int) $actor->id) {
            $request->session()->forget('releaselens.active_organization_id');
        }
    }

    /** @return array<string, mixed> */
    private function memberPayload(object $member): array
    {
        return [
            'id' => (int) $member->membership_id,
            'user' => [
                'id' => (int) $member->user_id,
                'name' => $member->name,
                'email' => $member->email,
                'timezone' => $member->timezone,
            ],
            'role' => $member->role,
            'joined_at' => $member->joined_at,
        ];
    }

    private function assertOwner(User $actor, int $organizationId): void
    {
        $membership = $this->organizations->membershipForUser(
            $organizationId,
            $actor->id,
        );

        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel(
                'Organization',
                [$organizationId],
            );
        }

        if ($membership->role !== OrganizationRole::Owner->value) {
            throw new AuthorizationException(
                'Only workspace Owners can manage members.',
            );
        }
    }

    private function memberOrFail(
        int $organizationId,
        int $membershipId,
    ): object {
        $member = $this->organizations->memberById(
            $organizationId,
            $membershipId,
        );

        if ($member === null) {
            throw (new ModelNotFoundException)->setModel(
                'OrganizationMember',
                [$membershipId],
            );
        }

        return $member;
    }

    private function throwLastOwnerException(): never
    {
        throw new OrganizationRuleException(
            'LAST_OWNER_REQUIRED',
            'Promote another Owner before demoting or removing the final Owner.',
            409,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function audit(
        int $organizationId,
        User $actor,
        string $eventType,
        object $member,
        array $metadata,
        Request $request,
    ): void {
        $this->organizations->recordAuditEvent(
            $organizationId,
            $actor->id,
            $eventType,
            $member->membership_id,
            $metadata,
            $request->ip(),
            $request->userAgent(),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->organizations->slugExists($slug)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
