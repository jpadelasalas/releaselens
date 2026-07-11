<?php

namespace App\Modules\Incidents\Repositories;

use App\Modules\Incidents\Contracts\IncidentLinkRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncidentLinkRepository implements IncidentLinkRepositoryInterface
{
    public function link(int $incidentId, string $linkableType, int $linkableId): object
    {
        $now = now();
        $id = (int) DB::table('incident_links')->insertGetId([
            'incident_id' => $incidentId,
            'linkable_type' => $linkableType,
            'linkable_id' => $linkableId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('incident_links')->find($id);
    }

    public function unlink(int $incidentId, string $linkableType, int $linkableId): void
    {
        DB::table('incident_links')
            ->where('incident_id', $incidentId)
            ->where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId)
            ->delete();
    }

    public function forIncident(int $incidentId): Collection
    {
        return DB::table('incident_links')
            ->where('incident_id', $incidentId)
            ->orderBy('id')
            ->get();
    }
}
