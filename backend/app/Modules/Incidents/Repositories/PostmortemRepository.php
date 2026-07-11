<?php

namespace App\Modules\Incidents\Repositories;

use App\Modules\Incidents\Contracts\PostmortemRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PostmortemRepository implements PostmortemRepositoryInterface
{
    public function find(int $incidentId): ?object
    {
        return DB::table('postmortems')
            ->where('incident_id', $incidentId)
            ->first();
    }

    public function upsert(int $incidentId, array $attributes): object
    {
        $now = now();
        $existing = $this->find($incidentId);

        if ($existing === null) {
            $id = (int) DB::table('postmortems')->insertGetId([
                'incident_id' => $incidentId,
                ...$attributes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('postmortems')->find($id);
        }

        DB::table('postmortems')->where('id', $existing->id)->update([
            ...$attributes,
            'updated_at' => $now,
        ]);

        return DB::table('postmortems')->find($existing->id);
    }

    public function publish(int $incidentId): object
    {
        $now = now();
        DB::table('postmortems')->where('incident_id', $incidentId)->update([
            'is_published' => true,
            'published_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($incidentId);
    }
}
