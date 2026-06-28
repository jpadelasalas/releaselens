<?php

namespace App\Modules\PullRequests\Services;

use App\Modules\PullRequests\Contracts\PullRequestRepositoryInterface;
use Carbon\CarbonImmutable;

class PullRequestExplorerService
{
    public function __construct(
        private readonly PullRequestRepositoryInterface $pullRequests
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(int $organizationId, array $filters): array
    {
        $paginator = $this->pullRequests->paginateForOrganization(
            $organizationId,
            $filters,
            $filters['per_page'],
        );
        $records = collect($paginator->items());
        $reviewedIds = array_flip(
            $this->pullRequests->qualifyingReviewPullRequestIds(
                $records->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()
            )
        );
        $anchor = CarbonImmutable::parse(
            $filters['now'] ?? config('releaselens.demo.anchor_date')
        )->utc();

        return [
            'records' => $records
                ->map(fn (object $record): array => $this->formatRecord(
                    $record,
                    isset($reviewedIds[(int) $record->id]),
                    $anchor,
                ))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'applied_filters' => [
                    'repository_ids' => array_values($filters['repository_ids'] ?? []),
                    'date_from' => $filters['date_from'],
                    'date_to' => $filters['date_to'],
                    'review_status' => $filters['review_status'] ?? null,
                    'attention' => $filters['attention'] ?? false,
                    'state' => $filters['state'] ?? null,
                    'age_bucket' => $filters['age_bucket'] ?? null,
                    'size_bucket' => $filters['size_bucket'] ?? null,
                    'event' => $filters['event'] ?? null,
                    'week' => $filters['week'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(
        object $record,
        bool $hasQualifyingReview,
        CarbonImmutable $anchor
    ): array {
        $ageHours = (int) CarbonImmutable::parse($record->created_at_github)
            ->utc()
            ->diffInHours($anchor);
        $changeSize = (int) $record->additions + (int) $record->deletions;
        $reviewStatus = match (true) {
            (bool) $record->is_draft => 'DRAFT',
            $hasQualifyingReview => 'REVIEWED',
            $record->state === 'open' => 'WAITING',
            default => 'UNREVIEWED',
        };
        $attentionReasons = [];

        if ($reviewStatus === 'WAITING') {
            $attentionReasons[] = 'WAITING_FOR_FIRST_REVIEW';
        }

        if (! (bool) $record->is_draft && $record->state === 'open' && $ageHours > 168) {
            $attentionReasons[] = 'STALE_OPEN_PR';
        }

        if ($changeSize > 500) {
            $attentionReasons[] = 'LARGE_PR';
        }

        return [
            'id' => (int) $record->id,
            'repository' => [
                'id' => (int) $record->repository_id,
                'name' => $record->repository_name,
            ],
            'number' => (int) $record->number,
            'title' => $record->title,
            'author' => $record->author_login,
            'state' => $record->state,
            'is_draft' => (bool) $record->is_draft,
            'age_hours' => $ageHours,
            'change_size' => $changeSize,
            'review_status' => $reviewStatus,
            'attention_reasons' => $attentionReasons,
            'external_url' => $record->html_url,
        ];
    }
}
