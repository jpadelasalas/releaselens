<?php

namespace App\Modules\Identity\Repositories;

use App\Models\User;
use App\Modules\Identity\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function findByNormalizedEmail(string $normalizedEmail): ?User
    {
        return User::query()->where('normalized_email', $normalizedEmail)->first();
    }

    public function membershipsForUser(int $userId): Collection
    {
        return DB::table('organization_members')
            ->join('organizations', 'organizations.id', '=', 'organization_members.organization_id')
            ->where('organization_members.user_id', $userId)
            ->orderBy('organizations.name')
            ->get([
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.timezone',
                'organization_members.role',
            ]);
    }
}
