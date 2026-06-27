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
            $this->applyWaitingFilter($query);
        }

        if (($filters['attention'] ?? false) === true) {
            $this->applyAttentionFilter($query);
        }

        if (($filters['state'] ?? null) === 'closed_without_merge') {
            $query
                ->where('pull_requests.state', 'closed')
                ->whereNull('pull_requests.merged_at');
        }

        if (isset($filters['age_bucket'])) {
            $this->applyAgeBucketFilter($query, $filters['age_bucket']);
        }

        if (isset($filters['size_bucket'])) {
            $this->applySizeBucketFilter($query, $filters['size_bucket']);
        }

        if (isset($filters['event'], $filters['week'])) {
            $this->applyWeeklyEventFilter(
                $query,
                $filters['event'],
                $filters['week'],
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

    private function applyWaitingFilter(Builder $query): void
    {
        $query
            ->where('pull_requests.state', 'open')
            ->where('pull_requests.is_draft', false)
            ->whereNotExists(
                fn (Builder $reviewQuery): Builder => $this->qualifyingReviewQuery(
                    $reviewQuery
                )
            );
    }

    private function applyAttentionFilter(Builder $query): void
    {
        $staleBoundary = CarbonImmutable::parse(
            config('releaselens.demo.anchor_date')
        )->utc()->subHours(168);

        $query
            ->where('pull_requests.state', 'open')
            ->where(function (Builder $attentionQuery) use ($staleBoundary): void {
                $attentionQuery
                    ->where(function (Builder $waitingQuery): void {
                        $waitingQuery
                            ->where('pull_requests.is_draft', false)
                            ->whereNotExists(
                                fn (Builder $reviewQuery): Builder => $this->qualifyingReviewQuery(
                                    $reviewQuery
                                )
                            );
                    })
                    ->orWhere(function (Builder $staleQuery) use ($staleBoundary): void {
                        $staleQuery
                            ->where('pull_requests.is_draft', false)
                            ->where('pull_requests.created_at_github', '<', $staleBoundary);
                    })
                    ->orWhereRaw(
                        '(pull_requests.additions + pull_requests.deletions) > 500'
                    );
            });
    }

    private function applyAgeBucketFilter(Builder $query, string $bucket): void
    {
        $anchor = CarbonImmutable::parse(
            config('releaselens.demo.anchor_date')
        )->utc();

        $query->where('pull_requests.state', 'open');

        match ($bucket) {
            'under_1_day' => $query->where(
                'pull_requests.created_at_github',
                '>=',
                $anchor->subHours(24),
            ),
            '1_to_3_days' => $query
                ->where('pull_requests.created_at_github', '<', $anchor->subHours(24))
                ->where('pull_requests.created_at_github', '>=', $anchor->subHours(72)),
            '3_to_7_days' => $query
                ->where('pull_requests.created_at_github', '<', $anchor->subHours(72))
                ->where('pull_requests.created_at_github', '>=', $anchor->subHours(168)),
            'over_7_days' => $query->where(
                'pull_requests.created_at_github',
                '<',
                $anchor->subHours(168),
            ),
        };
    }

    private function applySizeBucketFilter(Builder $query, string $bucket): void
    {
        $sizeExpression = '(pull_requests.additions + pull_requests.deletions)';

        match ($bucket) {
            'xs' => $query->whereRaw("{$sizeExpression} <= 50"),
            'small' => $query
                ->whereRaw("{$sizeExpression} > 50")
                ->whereRaw("{$sizeExpression} <= 200"),
            'medium' => $query
                ->whereRaw("{$sizeExpression} > 200")
                ->whereRaw("{$sizeExpression} <= 500"),
            'large' => $query->whereRaw("{$sizeExpression} > 500"),
        };
    }

    private function applyWeeklyEventFilter(
        Builder $query,
        string $event,
        string $week
    ): void {
        $weekStart = CarbonImmutable::parse($week)->utc()->startOfDay();
        $weekEnd = $weekStart->addDays(6)->endOfDay();
        $column = $event === 'merged'
            ? 'pull_requests.merged_at'
            : 'pull_requests.created_at_github';

        $query->whereBetween($column, [$weekStart, $weekEnd]);
    }
}
