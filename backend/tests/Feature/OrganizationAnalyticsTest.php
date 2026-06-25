<?php

namespace Tests\Feature;

use App\Modules\Analytics\Services\OrganizationAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private int $githubPullRequestId = 10_000_000;

    private int $githubReviewId = 20_000_000;

    public function test_summary_metrics_apply_qualifying_review_rules(): void
    {
        $organizationId = $this->organization();
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('author');
        $reviewerId = $this->githubUser('reviewer');
        $botId = $this->githubUser('ci-bot[bot]', isBot: true);
        $now = '2026-06-19T12:00:00Z';

        $first = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 1,
            createdAt: '2026-06-17T12:00:00Z',
            state: 'closed',
            mergedAt: '2026-06-18T18:00:00Z',
        );
        $this->review($first, $reviewerId, 'approved', '2026-06-17T22:00:00Z');

        $second = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 2,
            createdAt: '2026-06-16T12:00:00Z',
            state: 'closed',
            mergedAt: '2026-06-18T14:00:00Z',
        );
        $this->review($second, $reviewerId, 'changes_requested', '2026-06-17T08:00:00Z');

        $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 3,
            createdAt: '2026-06-19T06:00:00Z',
        );

        $selfReviewed = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 4,
            createdAt: '2026-06-18T06:00:00Z',
        );
        $this->review($selfReviewed, $authorId, 'approved', '2026-06-18T08:00:00Z');

        $botReviewed = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 5,
            createdAt: '2026-06-18T07:00:00Z',
        );
        $this->review($botReviewed, $botId, 'approved', '2026-06-18T09:00:00Z');

        $pendingReviewed = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 6,
            createdAt: '2026-06-18T08:00:00Z',
        );
        $this->review($pendingReviewed, $reviewerId, 'pending', null);

        $dismissedReviewed = $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 7,
            createdAt: '2026-06-18T09:00:00Z',
        );
        $this->review($dismissedReviewed, $reviewerId, 'dismissed', '2026-06-18T10:00:00Z');

        $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 8,
            createdAt: '2026-06-18T10:00:00Z',
            isDraft: true,
        );

        $this->pullRequest(
            repositoryId: $repositoryId,
            authorId: $authorId,
            number: 9,
            createdAt: '2026-06-15T12:00:00Z',
            state: 'closed',
            closedAt: '2026-06-16T12:00:00Z',
        );

        $analytics = app(OrganizationAnalyticsService::class)->dashboard($organizationId, [
            'now' => $now,
        ]);

        $this->assertSame(15.0, $analytics['summary']['median_first_review_hours']);
        $this->assertSame(2, $analytics['summary']['median_first_review_sample_size']);
        $this->assertSame(40.0, $analytics['summary']['median_merge_hours']);
        $this->assertSame(2, $analytics['summary']['median_merge_sample_size']);
        $this->assertSame(5, $analytics['summary']['waiting_for_first_review']);
        $this->assertSame(1, $analytics['summary']['closed_without_merge']);
    }

    public function test_buckets_and_filters_are_applied_consistently(): void
    {
        $organizationId = $this->organization();
        $includedRepositoryId = $this->repository($organizationId, 'customer-portal');
        $excludedRepositoryId = $this->repository($organizationId, 'billing-api');
        $authorId = $this->githubUser('author');

        $this->pullRequest(
            repositoryId: $includedRepositoryId,
            authorId: $authorId,
            number: 10,
            createdAt: '2026-06-18T12:00:00Z',
            additions: 50,
        );
        $this->pullRequest(
            repositoryId: $includedRepositoryId,
            authorId: $authorId,
            number: 11,
            createdAt: '2026-06-16T12:00:00Z',
            additions: 200,
        );
        $this->pullRequest(
            repositoryId: $includedRepositoryId,
            authorId: $authorId,
            number: 12,
            createdAt: '2026-06-12T12:00:00Z',
            additions: 500,
        );
        $this->pullRequest(
            repositoryId: $includedRepositoryId,
            authorId: $authorId,
            number: 13,
            createdAt: '2026-06-12T11:00:00Z',
            additions: 501,
        );
        $this->pullRequest(
            repositoryId: $excludedRepositoryId,
            authorId: $authorId,
            number: 14,
            createdAt: '2026-06-18T12:00:00Z',
            additions: 501,
        );
        $this->pullRequest(
            repositoryId: $includedRepositoryId,
            authorId: $authorId,
            number: 15,
            createdAt: '2026-05-01T12:00:00Z',
            additions: 501,
        );

        $analytics = app(OrganizationAnalyticsService::class)->dashboard($organizationId, [
            'repository_ids' => [$includedRepositoryId],
            'date_from' => '2026-06-01T00:00:00Z',
            'date_to' => '2026-06-19T12:00:00Z',
            'now' => '2026-06-19T12:00:00Z',
        ]);

        $this->assertSame(1, $analytics['selected_repository_count']);
        $this->assertSame(
            [
                ['key' => 'under_1_day', 'label' => 'Under 1 day', 'count' => 1],
                ['key' => '1_to_3_days', 'label' => '1-3 days', 'count' => 1],
                ['key' => '3_to_7_days', 'label' => '3-7 days', 'count' => 1],
                ['key' => 'over_7_days', 'label' => 'Over 7 days', 'count' => 1],
            ],
            $analytics['distributions']['open_pr_age'],
        );
        $this->assertSame(
            [
                ['key' => 'xs', 'label' => '1-50 lines', 'count' => 1],
                ['key' => 'small', 'label' => '51-200 lines', 'count' => 1],
                ['key' => 'medium', 'label' => '201-500 lines', 'count' => 1],
                ['key' => 'large', 'label' => '501+ lines', 'count' => 1],
            ],
            $analytics['distributions']['pr_size'],
        );
    }

    private function organization(): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Northstar Engineering',
            'slug' => 'northstar-engineering',
            'timezone' => 'UTC',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(int $organizationId, string $name = 'customer-portal'): int
    {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => random_int(1_000_000, 9_999_999),
            'name' => $name,
            'full_name' => "northstar/{$name}",
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function githubUser(string $login, bool $isBot = false): int
    {
        return (int) DB::table('github_users')->insertGetId([
            'github_user_id' => random_int(1_000_000, 9_999_999),
            'login' => $login,
            'type' => $isBot ? 'Bot' : 'User',
            'is_bot' => $isBot,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pullRequest(
        int $repositoryId,
        int $authorId,
        int $number,
        string $createdAt,
        string $state = 'open',
        bool $isDraft = false,
        ?string $closedAt = null,
        ?string $mergedAt = null,
        int $additions = 100,
        int $deletions = 0,
    ): int {
        $createdAt = CarbonImmutable::parse($createdAt)->utc();

        return (int) DB::table('pull_requests')->insertGetId([
            'repository_id' => $repositoryId,
            'github_pull_request_id' => $this->githubPullRequestId++,
            'number' => $number,
            'title' => "PR {$number}",
            'state' => $state,
            'is_draft' => $isDraft,
            'author_github_user_id' => $authorId,
            'base_ref' => 'main',
            'head_ref' => "feature/{$number}",
            'additions' => $additions,
            'deletions' => $deletions,
            'changed_files' => 1,
            'commits_count' => 1,
            'comments_count' => 0,
            'created_at_github' => $createdAt,
            'updated_at_github' => $closedAt ?? $mergedAt ?? $createdAt,
            'closed_at' => $closedAt,
            'merged_at' => $mergedAt,
            'created_at' => $createdAt,
            'updated_at' => $closedAt ?? $mergedAt ?? $createdAt,
        ]);
    }

    private function review(
        int $pullRequestId,
        int $reviewerId,
        string $state,
        ?string $submittedAt
    ): void {
        DB::table('pull_request_reviews')->insert([
            'pull_request_id' => $pullRequestId,
            'github_review_id' => $this->githubReviewId++,
            'reviewer_github_user_id' => $reviewerId,
            'state' => $state,
            'submitted_at' => $submittedAt,
            'github_updated_at' => $submittedAt,
            'created_at' => $submittedAt ?? now(),
            'updated_at' => $submittedAt ?? now(),
        ]);
    }
}
