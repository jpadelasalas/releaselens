<?php

namespace App\Modules\Analytics\Contracts;

use App\Modules\Analytics\Enums\AnalyticsDateBasis;
use Illuminate\Support\Collection;

interface OrganizationAnalyticsRepositoryInterface
{
    /**
     * @param  array{
     *     repository_ids?: array<int, int>,
     *     date_from?: string,
     *     date_to?: string
     * }  $filters
     * @return Collection<int, object>
     */
    public function pullRequests(
        int $organizationId,
        array $filters,
        AnalyticsDateBasis $dateBasis = AnalyticsDateBasis::Created,
    ): Collection;

    /**
     * @param  Collection<int, object>  $pullRequests
     * @return Collection<int, object>
     */
    public function reviewsForPullRequests(Collection $pullRequests): Collection;

    /**
     * @param  array<int, int>  $repositoryIds
     */
    public function selectedRepositoryCount(int $organizationId, array $repositoryIds): int;

    /**
     * @param  array<int, int>  $repositoryIds
     */
    public function freshnessTimestamp(int $organizationId, array $repositoryIds): ?string;
}
