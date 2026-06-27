<?php

namespace App\Modules\Organizations\Contracts;

use Illuminate\Support\Collection;

interface OrganizationWorkspaceRepositoryInterface
{
    public function slugExists(string $slug): bool;

    public function createWithOwner(
        int $userId,
        string $name,
        string $slug,
        string $timezone,
    ): object;

    /** @return Collection<int, object> */
    public function membershipsForUser(int $userId): Collection;

    public function membershipForUser(int $organizationId, int $userId): ?object;
}
