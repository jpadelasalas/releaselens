<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleaseChecklistRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReleaseChecklistRepository implements ReleaseChecklistRepositoryInterface
{
    public function add(int $releaseId, string $label, bool $isRequired): object
    {
        $now = now();
        $position = 1 + (int) DB::table('release_checklist_items')
            ->where('release_id', $releaseId)
            ->max('position');

        $id = (int) DB::table('release_checklist_items')->insertGetId([
            'release_id' => $releaseId,
            'label' => $label,
            'is_required' => $isRequired,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('release_checklist_items')->find($id);
    }

    public function forRelease(int $releaseId): Collection
    {
        return DB::table('release_checklist_items')
            ->where('release_id', $releaseId)
            ->orderBy('position')
            ->get();
    }

    public function find(int $releaseId, int $itemId): ?object
    {
        return DB::table('release_checklist_items')
            ->where('release_id', $releaseId)
            ->where('id', $itemId)
            ->first();
    }

    public function complete(int $itemId, int $completedByUserId): object
    {
        DB::table('release_checklist_items')->where('id', $itemId)->update([
            'completed_at' => now(),
            'completed_by_user_id' => $completedByUserId,
            'updated_at' => now(),
        ]);

        return DB::table('release_checklist_items')->find($itemId);
    }

    public function uncomplete(int $itemId): object
    {
        DB::table('release_checklist_items')->where('id', $itemId)->update([
            'completed_at' => null,
            'completed_by_user_id' => null,
            'updated_at' => now(),
        ]);

        return DB::table('release_checklist_items')->find($itemId);
    }

    public function remove(int $releaseId, int $itemId): void
    {
        DB::table('release_checklist_items')
            ->where('release_id', $releaseId)
            ->where('id', $itemId)
            ->delete();
    }

    public function hasIncompleteRequiredItems(int $releaseId): bool
    {
        return DB::table('release_checklist_items')
            ->where('release_id', $releaseId)
            ->where('is_required', true)
            ->whereNull('completed_at')
            ->exists();
    }
}
