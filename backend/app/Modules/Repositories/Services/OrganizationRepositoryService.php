<?php

namespace App\Modules\Repositories\Services;

use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;

class OrganizationRepositoryService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $repositories
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $organizationId): array
    {
        return $this->repositories
            ->listForOrganization($organizationId)
            ->map(fn (object $repository): array => [
                'id' => (int) $repository->id,
                'name' => $repository->name,
                'full_name' => $repository->full_name,
                'last_successful_sync_at' => $repository->last_successful_sync_at,
            ])
            ->all();
    }
}
