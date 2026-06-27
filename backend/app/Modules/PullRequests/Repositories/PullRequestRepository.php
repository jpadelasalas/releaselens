<?php

namespace App\Modules\PullRequests\Repositories;

use App\Modules\PullRequests\Contracts\PullRequestRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PullRequestRepository implements PullRequestRepositoryInterface
{
    public function paginateForOrganization(
        int $organizationId,
        array $filters,
        int $perPage
    ): LengthAwarePaginator {
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
                'pull_requests.html_url',
                'pull_requests.state',
                'pull_requests.is_draft',
                'authors.login as author_login',
                'pull_requests.additions',
                'pull_requests.deletions',
                'pull_requests.created_at_github',
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

        if (($filters['review_status'] ?? null) === 'waiting') {
            $query
                ->where('pull_requests.state', 'open')
                ->where('pull_requests.is_draft', false)
                ->whereNotExists(
                    fn (Builder $reviewQuery): Builder => $this->qualifyingReviewQuery(
                        $reviewQuery
                    )
                );
        }

        return $query
            ->orderBy('pull_requests.created_at_github')
            ->orderBy('pull_requests.id')
            ->paginate($perPage);
    }

    public function qualifyingReviewPullRequestIds(array $pullRequestIds): array
    {
        if ($pullRequestIds === []) {
            return [];
        }

        return DB::table('pull_request_reviews as qualifying_reviews')
            ->join('github_users as reviewers', 'reviewers.id', '=', 'qualifying_reviews.reviewer_github_user_id')
            ->join('pull_requests', 'pull_requests.id', '=', 'qualifying_reviews.pull_request_id')
            ->whereIn('qualifying_reviews.pull_request_id', $pullRequestIds)
            ->whereNotNull('qualifying_reviews.submitted_at')
            ->whereNotIn('qualifying_reviews.state', ['pending', 'dismissed'])
            ->where('reviewers.is_bot', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('pull_requests.author_github_user_id')
                    ->orWhereColumn(
                        'qualifying_reviews.reviewer_github_user_id',
                        '!=',
                        'pull_requests.author_github_user_id',
                    );
            })
            ->distinct()
            ->pluck('qualifying_reviews.pull_request_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function qualifyingReviewQuery(Builder $query): Builder
    {
        return $query
            ->selectRaw('1')
            ->from('pull_request_reviews as qualifying_reviews')
            ->join('github_users as reviewers', 'reviewers.id', '=', 'qualifying_reviews.reviewer_github_user_id')
            ->whereColumn(
                'qualifying_reviews.pull_request_id',
                'pull_requests.id',
            )
            ->whereNotNull('qualifying_reviews.submitted_at')
            ->whereNotIn('qualifying_reviews.state', ['pending', 'dismissed'])
            ->where('reviewers.is_bot', false)
            ->where(function (Builder $reviewerQuery): void {
                $reviewerQuery
                    ->whereNull('pull_requests.author_github_user_id')
                    ->orWhereColumn(
                        'qualifying_reviews.reviewer_github_user_id',
                        '!=',
                        'pull_requests.author_github_user_id',
                    );
            });
    }
}
