<?php

namespace App\Modules\Repositories\Repositories;

use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function listForOrganization(int $organizationId): Collection
    {
        return DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'full_name',
                'last_successful_sync_at',
            ]);
    }
}
