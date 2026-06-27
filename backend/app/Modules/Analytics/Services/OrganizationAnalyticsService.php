<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Contracts\OrganizationAnalyticsRepositoryInterface;
use App\Modules\Analytics\Enums\AnalyticsDateBasis;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OrganizationAnalyticsService
{
    public function __construct(
        private readonly OrganizationAnalyticsRepositoryInterface $repository
    ) {}

    /**
     * @param  array{
     *     repository_ids?: array<int, int>,
     *     date_from?: string,
     *     date_to?: string,
     *     now?: string
     * }  $filters
     * @return array<string, mixed>
     */
    public function dashboard(int $organizationId, array $filters = []): array
    {
        $context = $this->context(
            $organizationId,
            $filters,
            includeMerged: true,
            includeClosed: true,
        );

        return [
            'applied_filters' => $this->appliedFilters($filters),
            'selected_repository_count' => $this->repository->selectedRepositoryCount(
                $organizationId,
                $filters['repository_ids'] ?? [],
            ),
            'demo_freshness_at' => $this->repository->freshnessTimestamp(
                $organizationId,
                $filters['repository_ids'] ?? [],
            ),
            'summary' => $this->summaryFromContext($context),
            'distributions' => $this->distributionsFromContext($context),
            'trends' => $this->trendsFromContext($context),
            'attention' => $this->attentionFromContext($context),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(int $organizationId, array $filters = []): array
    {
        $context = $this->context(
            $organizationId,
            $filters,
            includeMerged: true,
            includeClosed: true,
        );

        return $this->withMeta(
            $organizationId,
            $filters,
            ['metrics' => $this->summaryFromContext($context)],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function trends(int $organizationId, array $filters = []): array
    {
        $context = $this->context(
            $organizationId,
            $filters,
            includeReviews: false,
            includeMerged: true,
        );

        return $this->withMeta(
            $organizationId,
            $filters,
            ['series' => $this->trendsFromContext($context)],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function distributions(int $organizationId, array $filters = []): array
    {
        $context = $this->context(
            $organizationId,
            $filters,
            includeReviews: false,
        );

        return $this->withMeta(
            $organizationId,
            $filters,
            ['buckets' => $this->distributionsFromContext($context)],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function attention(int $organizationId, array $filters = []): array
    {
        $context = $this->context($organizationId, $filters);

        return $this->withMeta(
            $organizationId,
            $filters,
            ['records' => $this->attentionFromContext($context)],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     pull_requests: Collection<int, object>,
     *     merged_pull_requests: Collection<int, object>,
     *     closed_pull_requests: Collection<int, object>,
     *     first_reviews: array<int, CarbonImmutable>,
     *     now: CarbonImmutable
     * }
     */
    private function context(
        int $organizationId,
        array $filters,
        bool $includeReviews = true,
        bool $includeMerged = false,
        bool $includeClosed = false,
    ): array {
        $pullRequests = $this->repository->pullRequests(
            $organizationId,
            $filters,
            AnalyticsDateBasis::Created,
        );

        return [
            'pull_requests' => $pullRequests,
            'merged_pull_requests' => $includeMerged
                ? $this->repository->pullRequests(
                    $organizationId,
                    $filters,
                    AnalyticsDateBasis::Merged,
                )
                : collect(),
            'closed_pull_requests' => $includeClosed
                ? $this->repository->pullRequests(
                    $organizationId,
                    $filters,
                    AnalyticsDateBasis::Closed,
                )
                : collect(),
            'first_reviews' => $includeReviews
                ? $this->firstQualifyingReviews($pullRequests)
                : [],
            'now' => CarbonImmutable::parse(
                $filters['now'] ?? config('releaselens.demo.anchor_date')
            )->utc(),
        ];
    }

    /**
     * @param  Collection<int, object>  $pullRequests
     * @return array<int, CarbonImmutable>
     */
    private function firstQualifyingReviews(Collection $pullRequests): array
    {
        $pullRequestById = $pullRequests->keyBy('id');

        return $this->repository
            ->reviewsForPullRequests($pullRequests)
            ->reduce(function (array $firstReviews, object $review) use ($pullRequestById): array {
                $pullRequest = $pullRequestById->get($review->pull_request_id);

                if (
                    $pullRequest === null ||
                    (int) $review->reviewer_github_user_id ===
                    (int) $pullRequest->author_github_user_id ||
                    isset($firstReviews[$review->pull_request_id])
                ) {
                    return $firstReviews;
                }

                $firstReviews[$review->pull_request_id] =
                    CarbonImmutable::parse($review->submitted_at)->utc();

                return $firstReviews;
            }, []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withMeta(int $organizationId, array $filters, array $payload): array
    {
        return [
            'applied_filters' => $this->appliedFilters($filters),
            'selected_repository_count' => $this->repository->selectedRepositoryCount(
                $organizationId,
                $filters['repository_ids'] ?? [],
            ),
            'demo_freshness_at' => $this->repository->freshnessTimestamp(
                $organizationId,
                $filters['repository_ids'] ?? [],
            ),
            ...$payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{repository_ids: array<int, int>, date_from: mixed, date_to: mixed}
     */
    private function appliedFilters(array $filters): array
    {
        return [
            'repository_ids' => array_values($filters['repository_ids'] ?? []),
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     pull_requests: Collection<int, object>,
     *     merged_pull_requests: Collection<int, object>,
     *     closed_pull_requests: Collection<int, object>,
     *     first_reviews: array<int, CarbonImmutable>,
     *     now: CarbonImmutable
     * }  $context
     * @return array<string, mixed>
     */
    private function summaryFromContext(array $context): array
    {
        $pullRequests = $context['pull_requests'];
        $mergedPullRequests = $context['merged_pull_requests'];
        $closedPullRequests = $context['closed_pull_requests'];
        $firstReviews = $context['first_reviews'];

        $firstReviewDurations = $pullRequests
            ->reject(fn (object $pullRequest): bool => (bool) $pullRequest->is_draft)
            ->filter(fn (object $pullRequest): bool => isset($firstReviews[$pullRequest->id]))
            ->map(fn (object $pullRequest): int => $this->hoursBetween(
                $pullRequest->created_at_github,
                $firstReviews[$pullRequest->id],
            ))
            ->values()
            ->all();

        $mergeDurations = $mergedPullRequests
            ->filter(fn (object $pullRequest): bool => $pullRequest->merged_at !== null)
            ->map(fn (object $pullRequest): int => $this->hoursBetween(
                $pullRequest->created_at_github,
                $pullRequest->merged_at,
            ))
            ->values()
            ->all();

        return [
            'median_first_review_hours' => $this->median($firstReviewDurations),
            'median_first_review_sample_size' => count($firstReviewDurations),
            'median_merge_hours' => $this->median($mergeDurations),
            'median_merge_sample_size' => count($mergeDurations),
            'waiting_for_first_review' => $pullRequests
                ->filter(fn (object $pullRequest): bool => $this->isWaitingForFirstReview(
                    $pullRequest,
                    $firstReviews,
                ))
                ->count(),
            'closed_without_merge' => $closedPullRequests
                ->filter(fn (object $pullRequest): bool => $pullRequest->state === 'closed' &&
                    $pullRequest->merged_at === null)
                ->count(),
            'attention_count' => count($this->attentionFromContext($context)),
        ];
    }

    /**
     * @param  array{
     *     pull_requests: Collection<int, object>,
     *     merged_pull_requests: Collection<int, object>
     * }  $context
     * @return array<string, mixed>
     */
    private function trendsFromContext(array $context): array
    {
        return [
            'opened_vs_merged_by_week' => $this->openedVersusMergedByWeek(
                $context['pull_requests'],
                $context['merged_pull_requests'],
            ),
        ];
    }

    /**
     * @param  array{pull_requests: Collection<int, object>, now: CarbonImmutable}  $context
     * @return array<string, mixed>
     */
    private function distributionsFromContext(array $context): array
    {
        return [
            'open_pr_age' => $this->openPullRequestAgeBuckets(
                $context['pull_requests'],
                $context['now'],
            ),
            'pr_size' => $this->pullRequestSizeBuckets($context['pull_requests']),
        ];
    }

    /**
     * @param  array{
     *     pull_requests: Collection<int, object>,
     *     first_reviews: array<int, CarbonImmutable>,
     *     now: CarbonImmutable
     * }  $context
     * @return array<int, array<string, mixed>>
     */
    private function attentionFromContext(array $context): array
    {
        $firstReviews = $context['first_reviews'];
        $now = $context['now'];

        return $context['pull_requests']
            ->filter(fn (object $pullRequest): bool => $pullRequest->state === 'open')
            ->map(function (object $pullRequest) use ($firstReviews, $now): ?array {
                $reasons = [];
                $ageHours = $this->hoursBetween($pullRequest->created_at_github, $now);
                $size = (int) $pullRequest->additions + (int) $pullRequest->deletions;

                if ($this->isWaitingForFirstReview($pullRequest, $firstReviews)) {
                    $reasons[] = 'WAITING_FOR_FIRST_REVIEW';
                }

                if (! (bool) $pullRequest->is_draft && $ageHours > 168) {
                    $reasons[] = 'STALE_OPEN_PR';
                }

                if ($size > 500) {
                    $reasons[] = 'LARGE_PR';
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'pull_request_id' => (int) $pullRequest->id,
                    'repository' => $pullRequest->repository_name,
                    'number' => (int) $pullRequest->number,
                    'title' => $pullRequest->title,
                    'author' => $pullRequest->author_login,
                    'age_hours' => $ageHours,
                    'change_size' => $size,
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->sortByDesc('age_hours')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $openedPullRequests
     * @param  Collection<int, object>  $mergedPullRequests
     * @return array<int, array{week: string, opened: int, merged: int}>
     */
    private function openedVersusMergedByWeek(
        Collection $openedPullRequests,
        Collection $mergedPullRequests,
    ): array {
        $weeks = [];

        foreach ($openedPullRequests as $pullRequest) {
            $openedWeek = $this->weekKey($pullRequest->created_at_github);
            $weeks[$openedWeek] ??= [
                'week' => $openedWeek,
                'opened' => 0,
                'merged' => 0,
            ];
            $weeks[$openedWeek]['opened']++;
        }

        foreach ($mergedPullRequests->whereNotNull('merged_at') as $pullRequest) {
            $mergedWeek = $this->weekKey($pullRequest->merged_at);
            $weeks[$mergedWeek] ??= [
                'week' => $mergedWeek,
                'opened' => 0,
                'merged' => 0,
            ];
            $weeks[$mergedWeek]['merged']++;
        }

        ksort($weeks);

        return array_values($weeks);
    }

    /**
     * @param  Collection<int, object>  $pullRequests
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function openPullRequestAgeBuckets(
        Collection $pullRequests,
        CarbonImmutable $now
    ): array {
        $buckets = $this->emptyBuckets([
            'under_1_day' => 'Under 1 day',
            '1_to_3_days' => '1-3 days',
            '3_to_7_days' => '3-7 days',
            'over_7_days' => 'Over 7 days',
        ]);

        foreach ($pullRequests->where('state', 'open') as $pullRequest) {
            $ageHours = $this->hoursBetween($pullRequest->created_at_github, $now);
            $key = match (true) {
                $ageHours <= 24 => 'under_1_day',
                $ageHours <= 72 => '1_to_3_days',
                $ageHours <= 168 => '3_to_7_days',
                default => 'over_7_days',
            };

            $buckets[$key]['count']++;
        }

        return array_values($buckets);
    }

    /**
     * @param  Collection<int, object>  $pullRequests
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function pullRequestSizeBuckets(Collection $pullRequests): array
    {
        $buckets = $this->emptyBuckets([
            'xs' => '1-50 lines',
            'small' => '51-200 lines',
            'medium' => '201-500 lines',
            'large' => '501+ lines',
        ]);

        foreach ($pullRequests as $pullRequest) {
            $size = (int) $pullRequest->additions + (int) $pullRequest->deletions;
            $key = match (true) {
                $size <= 50 => 'xs',
                $size <= 200 => 'small',
                $size <= 500 => 'medium',
                default => 'large',
            };

            $buckets[$key]['count']++;
        }

        return array_values($buckets);
    }

    /**
     * @param  array<string, string>  $definitions
     * @return array<string, array{key: string, label: string, count: int}>
     */
    private function emptyBuckets(array $definitions): array
    {
        $buckets = [];

        foreach ($definitions as $key => $label) {
            $buckets[$key] = [
                'key' => $key,
                'label' => $label,
                'count' => 0,
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<int, int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * @param  array<int, CarbonImmutable>  $firstReviews
     */
    private function isWaitingForFirstReview(
        object $pullRequest,
        array $firstReviews
    ): bool {
        return $pullRequest->state === 'open' &&
            ! (bool) $pullRequest->is_draft &&
            ! isset($firstReviews[$pullRequest->id]);
    }

    private function hoursBetween(mixed $start, mixed $end): int
    {
        return (int) CarbonImmutable::parse($start)
            ->utc()
            ->diffInHours(CarbonImmutable::parse($end)->utc());
    }

    private function weekKey(mixed $date): string
    {
        return CarbonImmutable::parse($date)
            ->utc()
            ->startOfWeek()
            ->toDateString();
    }
}
