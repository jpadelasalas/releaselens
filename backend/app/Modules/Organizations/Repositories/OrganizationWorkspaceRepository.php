<?php

namespace App\Modules\Organizations\Repositories;

use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationWorkspaceRepository implements OrganizationWorkspaceRepositoryInterface
{
    public function slugExists(string $slug): bool
    {
        return DB::table('organizations')->where('slug', $slug)->exists();
    }

    public function createWithOwner(
        int $userId,
        string $name,
        string $slug,
        string $timezone,
    ): object {
        return DB::transaction(function () use (
            $userId,
            $name,
            $slug,
            $timezone,
        ): object {
            $now = now();
            $organizationId = (int) DB::table('organizations')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'timezone' => $timezone,
                'is_demo' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('organization_members')->insert([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'role' => 'owner',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (object) [
                'id' => $organizationId,
                'name' => $name,
                'slug' => $slug,
                'timezone' => $timezone,
                'role' => 'owner',
            ];
        });
    }

    public function membershipsForUser(int $userId): Collection
    {
        return $this->membershipQuery()
            ->where('organization_members.user_id', $userId)
            ->orderBy('organizations.name')
            ->get();
    }

    public function membershipForUser(int $organizationId, int $userId): ?object
    {
        return $this->membershipQuery()
            ->where('organizations.id', $organizationId)
            ->where('organization_members.user_id', $userId)
            ->first();
    }

    public function membersForOrganization(int $organizationId): Collection
    {
        return $this->membersQuery()
            ->where('organization_members.organization_id', $organizationId)
            ->orderBy('users.name')
            ->get();
    }

    public function memberById(int $organizationId, int $membershipId): ?object
    {
        return $this->membersQuery()
            ->where('organization_members.organization_id', $organizationId)
            ->where('organization_members.id', $membershipId)
            ->first();
    }

    public function addMember(
        int $organizationId,
        int $userId,
        string $role,
    ): object {
        $now = now();
        $membershipId = (int) DB::table('organization_members')->insertGetId([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->memberById($organizationId, $membershipId);
    }

    public function updateMemberRole(int $membershipId, string $role): void
    {
        DB::table('organization_members')
            ->where('id', $membershipId)
            ->update(['role' => $role, 'updated_at' => now()]);
    }

    public function removeMember(int $membershipId): void
    {
        DB::table('organization_members')->where('id', $membershipId)->delete();
    }

    public function ownerCount(int $organizationId): int
    {
        return DB::table('organization_members')
            ->where('organization_id', $organizationId)
            ->where('role', 'owner')
            ->count();
    }

    public function recordAuditEvent(
        int $organizationId,
        int $actorUserId,
        string $eventType,
        int $membershipId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::table('audit_logs')->insert([
            'organization_id' => $organizationId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'auditable_type' => 'organization_member',
            'auditable_id' => $membershipId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function membershipQuery(): Builder
    {
        return DB::table('organization_members')
            ->join(
                'organizations',
                'organizations.id',
                '=',
                'organization_members.organization_id',
            )
            ->where('organizations.is_demo', false)
            ->select([
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.timezone',
                'organization_members.role',
            ]);
    }

    private function membersQuery(): Builder
    {
        return DB::table('organization_members')
            ->join('users', 'users.id', '=', 'organization_members.user_id')
            ->select([
                'organization_members.id as membership_id',
                'organization_members.organization_id',
                'organization_members.role',
                'organization_members.joined_at',
                'users.id as user_id',
                'users.name',
                'users.email',
                'users.timezone',
            ]);
    }
}
