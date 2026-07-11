<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Notifications\Contracts\NotificationPreferenceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function isEnabled(int $organizationId, int $userId, string $type): bool
    {
        $preference = DB::table('notification_preferences')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();

        return $preference === null || (bool) $preference->enabled;
    }

    public function listForUser(int $organizationId, int $userId): Collection
    {
        return DB::table('notification_preferences')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->get();
    }

    public function setEnabled(int $organizationId, int $userId, string $type, bool $enabled): object
    {
        $now = now();
        $existing = DB::table('notification_preferences')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();

        if ($existing === null) {
            $id = (int) DB::table('notification_preferences')->insertGetId([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'type' => $type,
                'enabled' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('notification_preferences')->find($id);
        }

        DB::table('notification_preferences')->where('id', $existing->id)->update([
            'enabled' => $enabled,
            'updated_at' => $now,
        ]);

        return DB::table('notification_preferences')->find($existing->id);
    }
}
