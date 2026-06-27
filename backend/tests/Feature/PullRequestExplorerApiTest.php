<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PullRequestExplorerApiTest extends TestCase
{
    use RefreshDatabase;

    private int $githubPullRequestId = 40_000_000;

    private int $githubReviewId = 50_000_000;

    public function test_waiting_filter_reconciles_with_dashboard_metric(): void
    {
        $organizationId = $this->organization();
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('author');
        $reviewerId = $this->githubUser('reviewer');
        $botId = $this->githubUser('ci[bot]', isBot: true);

        $this->pullRequest($repositoryId, $authorId, 1);

        $reviewed = $this->pullRequest($repositoryId, $authorId, 2);
        $this->review($reviewed, $reviewerId, 'approved');

        $botOnly = $this->pullRequest($repositoryId, $authorId, 3);
        $this->review($botOnly, $botId, 'approved');

        $selfOnly = $this->pullRequest($repositoryId, $authorId, 4);
        $this->review($selfOnly, $authorId, 'approved');

        $this->pullRequest($repositoryId, $authorId, 5, isDraft: true);

        $session = [
            'releaselens.context' => [
                'type' => 'demo',
                'session_id' => 'demo-session-id',
                'organization_id' => $organizationId,
                'organization_slug' => 'northstar-engineering',
            ],
        ];

        $summary = $this
            ->withSession($session)
            ->getJson("/api/v1/organizations/{$organizationId}/analytics/summary");

        $explorer = $this
            ->withSession($session)
            ->getJson(
                "/api/v1/organizations/{$organizationId}/pull-requests".
                '?review_status=waiting&per_page=2'
            );

        $summary->assertOk();
        $explorer
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.review_status', 'WAITING')
            ->assertJsonPath('data.1.review_status', 'WAITING');

        $this->assertSame(
            $summary->json('data.metrics.waiting_for_first_review'),
            $explorer->json('meta.total'),
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

    private function repository(int $organizationId): int
    {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => 60_000_000,
            'name' => 'customer-portal',
            'full_name' => 'northstar/customer-portal',
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
            'github_user_id' => random_int(70_000_000, 79_999_999),
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
        bool $isDraft = false
    ): int {
        return (int) DB::table('pull_requests')->insertGetId([
            'repository_id' => $repositoryId,
            'github_pull_request_id' => $this->githubPullRequestId++,
            'number' => $number,
            'title' => "PR {$number}",
            'state' => 'open',
            'is_draft' => $isDraft,
            'author_github_user_id' => $authorId,
            'base_ref' => 'main',
            'head_ref' => "feature/{$number}",
            'additions' => 80,
            'deletions' => 20,
            'changed_files' => 2,
            'commits_count' => 1,
            'comments_count' => 0,
            'created_at_github' => '2026-06-10T12:00:00Z',
            'updated_at_github' => '2026-06-10T12:00:00Z',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function review(
        int $pullRequestId,
        int $reviewerId,
        string $state
    ): void {
        DB::table('pull_request_reviews')->insert([
            'pull_request_id' => $pullRequestId,
            'github_review_id' => $this->githubReviewId++,
            'reviewer_github_user_id' => $reviewerId,
            'state' => $state,
            'submitted_at' => '2026-06-11T12:00:00Z',
            'github_updated_at' => '2026-06-11T12:00:00Z',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
