<?php

namespace App\Modules\Incidents\Repositories;

use App\Modules\Incidents\Contracts\IncidentActionItemRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncidentActionItemRepository implements IncidentActionItemRepositoryInterface
{
    public function add(int $incidentId, string $description, ?int $assignedToUserId): object
    {
        $now = now();
        $id = (int) DB::table('incident_action_items')->insertGetId([
            'incident_id' => $incidentId,
            'description' => $description,
            'assigned_to_user_id' => $assignedToUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('incident_action_items')->find($id);
    }

    public function find(int $incidentId, int $id): ?object
    {
        return DB::table('incident_action_items')
            ->where('incident_id', $incidentId)
            ->where('id', $id)
            ->first();
    }

    public function complete(int $id, int $completedByUserId): object
    {
        $now = now();
        DB::table('incident_action_items')->where('id', $id)->update([
            'is_completed' => true,
            'completed_at' => $now,
            'completed_by_user_id' => $completedByUserId,
            'updated_at' => $now,
        ]);

        return DB::table('incident_action_items')->find($id);
    }

    public function uncomplete(int $id): object
    {
        DB::table('incident_action_items')->where('id', $id)->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by_user_id' => null,
            'updated_at' => now(),
        ]);

        return DB::table('incident_action_items')->find($id);
    }

    public function remove(int $incidentId, int $id): void
    {
        DB::table('incident_action_items')
            ->where('incident_id', $incidentId)
            ->where('id', $id)
            ->delete();
    }

    public function forIncident(int $incidentId): Collection
    {
        return DB::table('incident_action_items')
            ->where('incident_id', $incidentId)
            ->orderBy('id')
            ->get();
    }
}
