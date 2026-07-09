<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleaseActivityRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReleaseActivityRepository implements ReleaseActivityRepositoryInterface
{
    public function record(int $releaseId, ?int $actorUserId, string $action, array $metadata = []): object
    {
        $now = now();
        $id = (int) DB::table('release_activities')->insertGetId([
            'release_id' => $releaseId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('release_activities')->find($id);
    }

    public function forRelease(int $releaseId): Collection
    {
        return DB::table('release_activities')
            ->where('release_id', $releaseId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }
}
