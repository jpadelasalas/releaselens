<?php

namespace App\Modules\Ai\Repositories;

use App\Modules\Ai\Contracts\AiGenerationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiGenerationRepository implements AiGenerationRepositoryInterface
{
    public function record(
        int $organizationId,
        int $releaseId,
        ?int $requestedByUserId,
        string $provider,
        string $status,
        array $inputFields,
        ?string $output,
        ?string $errorMessage,
    ): object {
        $now = now();
        $id = (int) DB::table('ai_generations')->insertGetId([
            'organization_id' => $organizationId,
            'release_id' => $releaseId,
            'requested_by_user_id' => $requestedByUserId,
            'provider' => $provider,
            'status' => $status,
            'input_fields' => json_encode($inputFields, JSON_THROW_ON_ERROR),
            'output' => $output,
            'error_message' => $errorMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('ai_generations')->find($id);
    }

    public function countForOrganizationSince(int $organizationId, \DateTimeInterface $since): int
    {
        return DB::table('ai_generations')
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function forRelease(int $releaseId): Collection
    {
        return DB::table('ai_generations')
            ->where('release_id', $releaseId)
            ->orderByDesc('id')
            ->get();
    }
}
