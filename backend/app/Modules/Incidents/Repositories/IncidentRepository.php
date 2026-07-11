<?php

namespace App\Modules\Incidents\Repositories;

use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Enums\IncidentState;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IncidentRepository implements IncidentRepositoryInterface
{
    public function create(int $organizationId, array $attributes): object
    {
        $now = now();
        $id = (int) DB::table('incidents')->insertGetId([
            'organization_id' => $organizationId,
            'state' => IncidentState::Investigating->value,
            'started_at' => $now,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('incidents')->find($id);
    }

    public function find(int $id): ?object
    {
        return DB::table('incidents')->find($id);
    }

    public function findForOrganization(int $organizationId, int $id): ?object
    {
        return DB::table('incidents')
            ->where('organization_id', $organizationId)
            ->where('id', $id)
            ->first();
    }

    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('incidents')->where('organization_id', $organizationId);

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function update(int $id, array $attributes): object
    {
        DB::table('incidents')->where('id', $id)->update([
            ...$attributes,
            'updated_at' => now(),
        ]);

        return DB::table('incidents')->find($id);
    }

    public function updateState(int $id, string $state, array $extra = []): void
    {
        DB::table('incidents')->where('id', $id)->update([
            ...$extra,
            'state' => $state,
            'updated_at' => now(),
        ]);
    }
}
