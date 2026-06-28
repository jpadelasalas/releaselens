<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PullRequestExplorerApiTest extends TestCase
{
    use RefreshDatabase;

    private int $githubPullRequestId = 40_000_000;

    private int $githubReviewId = 50_000_000;

    public function test_connected_explorer_uses_the_current_time_for_pull_request_age(): void
    {
        CarbonImmutable::setTestNow('2026-06-28T12:00:00Z');
        $organizationId = $this->organization(isDemo: false);
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('connected-author');
        $pullRequestId = $this->pullRequest($repositoryId, $authorId, 1);
        $user = User::query()->create([
            'name' => 'Connected User',
            'email' => 'connected@example.com',
            'normalized_email' => 'connected@example.com',
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);

        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'viewer',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pull_requests')
            ->where('id', $pullRequestId)
            ->update([
                'created_at_github' => '2026-06-27T06:00:00Z',
                'updated_at_github' => '2026-06-27T06:00:00Z',
            ]);

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organizationId}/pull-requests")
            ->assertOk()
            ->assertJsonPath('data.0.age_hours', 30);

        CarbonImmutable::setTestNow();
    }

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

    public function test_demo_drill_downs_reconcile_with_analytics(): void
    {
        $this->seed(DemoSeeder::class);

        $organizationId = (int) DB::table('organizations')
            ->where('slug', 'northstar-engineering')
            ->value('id');
        $session = [
            'releaselens.context' => [
                'type' => 'demo',
                'session_id' => 'demo-session-id',
                'organization_id' => $organizationId,
                'organization_slug' => 'northstar-engineering',
            ],
        ];
        $basePath = "/api/v1/organizations/{$organizationId}";

        $summary = $this->withSession($session)->getJson("{$basePath}/analytics/summary");
        $distributions = $this->withSession($session)->getJson(
            "{$basePath}/analytics/distributions"
        );
        $trends = $this->withSession($session)->getJson("{$basePath}/analytics/trends");

        $summary->assertOk();
        $distributions->assertOk();
        $trends->assertOk();

        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?state=closed_without_merge",
            (int) $summary->json('data.metrics.closed_without_merge'),
        );
        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?attention=1",
            (int) $summary->json('data.metrics.attention_count'),
        );

        foreach ($distributions->json('data.buckets.open_pr_age') as $bucket) {
            $this->assertExplorerTotal(
                $session,
                "{$basePath}/pull-requests?age_bucket={$bucket['key']}",
                (int) $bucket['count'],
            );
        }

        foreach ($distributions->json('data.buckets.pr_size') as $bucket) {
            $this->assertExplorerTotal(
                $session,
                "{$basePath}/pull-requests?size_bucket={$bucket['key']}",
                (int) $bucket['count'],
            );
        }

        $weeklySeries = $trends->json('data.series.opened_vs_merged_by_week');
        $openedPoint = collect($weeklySeries)->firstWhere('opened', '>', 0);
        $mergedPoint = collect($weeklySeries)->firstWhere('merged', '>', 0);

        $this->assertNotNull($openedPoint);
        $this->assertNotNull($mergedPoint);
        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?event=opened&week={$openedPoint['week']}",
            (int) $openedPoint['opened'],
        );
        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?event=merged&week={$mergedPoint['week']}",
            (int) $mergedPoint['merged'],
        );
    }

    public function test_event_drill_downs_use_the_same_date_basis_as_analytics(): void
    {
        $organizationId = $this->organization();
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('author');

        $mergedPullRequestId = $this->pullRequest($repositoryId, $authorId, 20);
        DB::table('pull_requests')
            ->where('id', $mergedPullRequestId)
            ->update([
                'state' => 'closed',
                'created_at_github' => '2026-05-01T00:00:00Z',
                'closed_at' => '2026-06-10T00:00:00Z',
                'merged_at' => '2026-06-10T00:00:00Z',
            ]);

        $closedPullRequestId = $this->pullRequest($repositoryId, $authorId, 21);
        DB::table('pull_requests')
            ->where('id', $closedPullRequestId)
            ->update([
                'state' => 'closed',
                'created_at_github' => '2026-05-02T00:00:00Z',
                'closed_at' => '2026-06-11T00:00:00Z',
                'merged_at' => null,
            ]);

        $session = [
            'releaselens.context' => [
                'type' => 'demo',
                'session_id' => 'demo-session-id',
                'organization_id' => $organizationId,
                'organization_slug' => 'northstar-engineering',
            ],
        ];
        $basePath = "/api/v1/organizations/{$organizationId}";
        $dateQuery = 'date_from=2026-06-01T00:00:00Z'.
            '&date_to=2026-06-19T23:59:59Z';

        $summary = $this->withSession($session)->getJson(
            "{$basePath}/analytics/summary?{$dateQuery}"
        );
        $trends = $this->withSession($session)->getJson(
            "{$basePath}/analytics/trends?{$dateQuery}"
        );

        $summary->assertOk();
        $trends->assertOk();
        $this->assertSame(1, $summary->json('data.metrics.closed_without_merge'));
        $this->assertSame(1, $summary->json('data.metrics.median_merge_sample_size'));

        $mergedWeek = collect(
            $trends->json('data.series.opened_vs_merged_by_week')
        )->firstWhere('week', '2026-06-08');

        $this->assertNotNull($mergedWeek);
        $this->assertSame(1, $mergedWeek['merged']);

        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?{$dateQuery}&state=closed_without_merge",
            1,
        );
        $this->assertExplorerTotal(
            $session,
            "{$basePath}/pull-requests?{$dateQuery}&event=merged&week=2026-06-08",
            1,
        );
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function assertExplorerTotal(
        array $session,
        string $url,
        int $expected
    ): void {
        $response = $this->withSession($session)->getJson($url);

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', $expected);
    }

    private function organization(bool $isDemo = true): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Northstar Engineering',
            'slug' => 'northstar-engineering',
            'timezone' => 'UTC',
            'is_demo' => $isDemo,
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
