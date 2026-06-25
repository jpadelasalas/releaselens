<?php

namespace App\Modules\Analytics\Repositories;

use App\Modules\Analytics\Contracts\OrganizationAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationAnalyticsRepository implements OrganizationAnalyticsRepositoryInterface
{
    /**
     * @param  array{
     *     repository_ids?: array<int, int>,
     *     date_from?: string,
     *     date_to?: string
     * }  $filters
     * @return Collection<int, object>
     */
    public function pullRequests(int $organizationId, array $filters): Collection
    {
        $query = DB::table('pull_requests')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->leftJoin('github_users as authors', 'authors.id', '=', 'pull_requests.author_github_user_id')
            ->where('repositories.organization_id', $organizationId)
            ->select([
                'pull_requests.id',
                'pull_requests.repository_id',
                'repositories.name as repository_name',
                'pull_requests.number',
                'pull_requests.title',
                'pull_requests.state',
                'pull_requests.is_draft',
                'pull_requests.author_github_user_id',
                'authors.login as author_login',
                'pull_requests.additions',
                'pull_requests.deletions',
                'pull_requests.created_at_github',
                'pull_requests.closed_at',
                'pull_requests.merged_at',
            ]);

        $repositoryIds = $filters['repository_ids'] ?? [];

        if ($repositoryIds !== []) {
            $query->whereIn('pull_requests.repository_id', $repositoryIds);
        }

        if (isset($filters['date_from'])) {
            $query->where(
                'pull_requests.created_at_github',
                '>=',
                CarbonImmutable::parse($filters['date_from'])->utc(),
            );
        }

        if (isset($filters['date_to'])) {
            $query->where(
                'pull_requests.created_at_github',
                '<=',
                CarbonImmutable::parse($filters['date_to'])->utc(),
            );
        }

        return $query
            ->orderBy('pull_requests.created_at_github')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $pullRequests
     * @return Collection<int, object>
     */
    public function reviewsForPullRequests(Collection $pullRequests): Collection
    {
        if ($pullRequests->isEmpty()) {
            return collect();
        }

        return DB::table('pull_request_reviews')
            ->join('github_users as reviewers', 'reviewers.id', '=', 'pull_request_reviews.reviewer_github_user_id')
            ->whereIn('pull_request_reviews.pull_request_id', $pullRequests->pluck('id'))
            ->whereNotNull('pull_request_reviews.submitted_at')
            ->whereNotIn('pull_request_reviews.state', ['pending', 'dismissed'])
            ->where('reviewers.is_bot', false)
            ->select([
                'pull_request_reviews.pull_request_id',
                'pull_request_reviews.reviewer_github_user_id',
                'pull_request_reviews.submitted_at',
            ])
            ->orderBy('pull_request_reviews.submitted_at')
            ->get();
    }

    /**
     * @param  array<int, int>  $repositoryIds
     */
    public function selectedRepositoryCount(int $organizationId, array $repositoryIds): int
    {
        $query = DB::table('repositories')
            ->where('organization_id', $organizationId);

        if ($repositoryIds !== []) {
            $query->whereIn('id', $repositoryIds);
        }

        return $query->count();
    }

    /**
     * @param  array<int, int>  $repositoryIds
     */
    public function freshnessTimestamp(int $organizationId, array $repositoryIds): ?string
    {
        $query = DB::table('repositories')
            ->where('organization_id', $organizationId);

        if ($repositoryIds !== []) {
            $query->whereIn('id', $repositoryIds);
        }

        $timestamp = $query->max('last_successful_sync_at');

        return $timestamp === null
            ? null
            : CarbonImmutable::parse($timestamp)->utc()->toIso8601String();
    }
}
