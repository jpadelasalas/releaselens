<?php

namespace App\Modules\Repositories\Contracts;

use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    /**
     * @return Collection<int, object>
     */
    public function listForOrganization(int $organizationId): Collection;

    /** @return array<int, int> */
    public function monitoredGitHubIds(
        int $organizationId,
        int $installationRecordId,
    ): array;

    /** @param array<int, array<string, mixed>> $repositories */
    public function replaceMonitoredSelection(
        int $organizationId,
        int $installationRecordId,
        array $repositories,
    ): void;

    public function updateMonitoring(
        int $organizationId,
        int $repositoryId,
        bool $enabled,
    ): ?object;
}
