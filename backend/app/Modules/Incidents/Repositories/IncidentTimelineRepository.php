<?php

namespace App\Modules\Incidents\Repositories;

use App\Modules\Incidents\Contracts\IncidentTimelineRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncidentTimelineRepository implements IncidentTimelineRepositoryInterface
{
    public function record(int $incidentId, ?int $actorUserId, string $entryType, string $message): object
    {
        $now = now();
        $id = (int) DB::table('incident_timeline_entries')->insertGetId([
            'incident_id' => $incidentId,
            'actor_user_id' => $actorUserId,
            'entry_type' => $entryType,
            'message' => $message,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('incident_timeline_entries')->find($id);
    }

    public function forIncident(int $incidentId): Collection
    {
        return DB::table('incident_timeline_entries')
            ->where('incident_id', $incidentId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }
}
