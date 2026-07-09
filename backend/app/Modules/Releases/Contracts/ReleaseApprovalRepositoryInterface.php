<?php

namespace App\Modules\Releases\Contracts;

use Illuminate\Support\Collection;

interface ReleaseApprovalRepositoryInterface
{
    public function record(int $releaseId, int $approverUserId): object;

    public function forRelease(int $releaseId): Collection;
}
