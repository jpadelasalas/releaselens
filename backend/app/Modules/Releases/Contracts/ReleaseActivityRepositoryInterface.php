<?php

namespace App\Modules\Releases\Contracts;

use Illuminate\Support\Collection;

interface ReleaseActivityRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(int $releaseId, ?int $actorUserId, string $action, array $metadata = []): object;

    public function forRelease(int $releaseId): Collection;
}
