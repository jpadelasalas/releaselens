<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(
        int $organizationId,
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?string $subjectType,
        ?int $subjectId,
        ?string $dedupKey,
    ): object {
        $now = now();
        $id = (int) DB::table('notifications')->insertGetId([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'dedup_key' => $dedupKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('notifications')->find($id);
    }

    public function existsWithinWindow(int $userId, string $dedupKey, int $windowMinutes): bool
    {
        return DB::table('notifications')
            ->where('user_id', $userId)
            ->where('dedup_key', $dedupKey)
            ->where('created_at', '>=', CarbonImmutable::now()->subMinutes($windowMinutes))
            ->exists();
    }

    public function forUser(int $organizationId, int $userId, bool $unreadOnly = false): Collection
    {
        $query = DB::table('notifications')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId);

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->orderByDesc('id')->get();
    }

    public function unreadCount(int $organizationId, int $userId): int
    {
        return DB::table('notifications')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(int $organizationId, int $userId, int $id): void
    {
        DB::table('notifications')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('id', $id)
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function markAllRead(int $organizationId, int $userId): void
    {
        DB::table('notifications')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
