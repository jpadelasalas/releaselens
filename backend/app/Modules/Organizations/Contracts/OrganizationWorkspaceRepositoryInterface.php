<?php

namespace App\Modules\Organizations\Contracts;

use Illuminate\Support\Collection;

interface OrganizationWorkspaceRepositoryInterface
{
    public function slugExists(string $slug): bool;

    public function createWithOwner(
        int $userId,
        string $name,
        string $slug,
        string $timezone,
    ): object;

    /** @return Collection<int, object> */
    public function membershipsForUser(int $userId): Collection;

    public function membershipForUser(int $organizationId, int $userId): ?object;

    /** @return Collection<int, object> */
    public function membersForOrganization(int $organizationId): Collection;

    public function memberById(int $organizationId, int $membershipId): ?object;

    public function addMember(
        int $organizationId,
        int $userId,
        string $role,
    ): object;

    public function updateMemberRole(int $membershipId, string $role): void;

    public function removeMember(int $membershipId): void;

    public function ownerCount(int $organizationId): int;

    public function recordAuditEvent(
        int $organizationId,
        int $actorUserId,
        string $eventType,
        int $membershipId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void;
}
