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
}
