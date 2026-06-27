<?php

namespace App\Modules\PullRequests\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface PullRequestRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<object>
     */
    public function paginateForOrganization(
        int $organizationId,
        array $filters,
        int $perPage
    ): LengthAwarePaginator;

    /**
     * @param  array<int, int>  $pullRequestIds
     * @return array<int, int>
     */
    public function qualifyingReviewPullRequestIds(array $pullRequestIds): array;
}
