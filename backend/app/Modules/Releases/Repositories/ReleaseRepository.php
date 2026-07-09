<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use Illuminate\Support\Facades\DB;

class ReleaseRepository implements ReleaseRepositoryInterface
{
    public function create(int $organizationId, array $attributes): object
    {
        $now = now();
        $id = (int) DB::table('releases')->insertGetId([
            'organization_id' => $organizationId,
            'state' => ReleaseState::Draft->value,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('releases')->find($id);
    }

    public function find(int $id): ?object
    {
        return DB::table('releases')->find($id);
    }

    public function findForOrganization(int $organizationId, int $id): ?object
    {
        return DB::table('releases')
            ->where('organization_id', $organizationId)
            ->where('id', $id)
            ->first();
    }
}
