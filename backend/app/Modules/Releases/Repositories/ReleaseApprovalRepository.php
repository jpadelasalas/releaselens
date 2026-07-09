<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReleaseApprovalRepository implements ReleaseApprovalRepositoryInterface
{
    public function record(int $releaseId, int $approverUserId, int $approvalGeneration): object
    {
        $now = now();
        $id = (int) DB::table('release_approvals')->insertGetId([
            'release_id' => $releaseId,
            'approver_user_id' => $approverUserId,
            'approval_generation' => $approvalGeneration,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('release_approvals')->find($id);
    }

    public function forRelease(int $releaseId): Collection
    {
        return DB::table('release_approvals')
            ->where('release_id', $releaseId)
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get();
    }
}
