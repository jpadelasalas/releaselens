<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseApprovalMode;
use Illuminate\Support\Facades\DB;

class ReleasePolicyRepository implements ReleasePolicyRepositoryInterface
{
    public function getForOrganization(int $organizationId): ?object
    {
        return DB::table('release_policies')
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function upsertForOrganization(int $organizationId, array $attributes): object
    {
        $now = now();
        $existing = $this->getForOrganization($organizationId);

        if ($existing === null) {
            $id = (int) DB::table('release_policies')->insertGetId([
                'organization_id' => $organizationId,
                'approval_mode' => ReleaseApprovalMode::SingleApprover->value,
                'allow_self_approval' => false,
                ...$attributes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('release_policies')->find($id);
        }

        DB::table('release_policies')
            ->where('organization_id', $organizationId)
            ->update([
                ...$attributes,
                'updated_at' => $now,
            ]);

        return DB::table('release_policies')->where('organization_id', $organizationId)->first();
    }
}
