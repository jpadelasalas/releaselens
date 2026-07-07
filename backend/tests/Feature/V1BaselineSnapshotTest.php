<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for the V2 migration program.
 *
 * This pins the exact V1 demo dataset (row counts and a content
 * fingerprint) produced by a fresh migrate + DemoSeeder run. Every V2
 * migration must keep this test green: if a future migration renames,
 * drops, or reshapes a column the seeder or this fixture depends on,
 * or otherwise changes V1 data, this test fails before it reaches
 * production. See docs/v2/delivery-plan.md T0.1 and V2-FR-BASE-005.
 */
class V1BaselineSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_FINGERPRINT = '30a4cad5b5ec40b8b0e4f67d4e92674c6312630deeb377069624ac429236fc11';

    public function test_v1_demo_dataset_matches_the_pinned_baseline(): void
    {
        config()->set('releaselens.demo.anchor_date', '2026-06-19T12:00:00Z');
        config()->set('releaselens.demo.random_seed', 1001);

        $this->seed(DemoSeeder::class);

        $organizationId = (int) DB::table('organizations')
            ->where('slug', 'northstar-engineering')
            ->value('id');

        $this->assertSame(1, DB::table('organizations')->where('is_demo', true)->count());
        $this->assertSame(4, DB::table('repositories')->where('organization_id', $organizationId)->count());
        $this->assertSame(10, DB::table('github_users')->count());
        $this->assertSame(192, DB::table('pull_requests')->count());
        $this->assertSame(4, DB::table('sync_runs')->count());
        $this->assertSame(1, DB::table('organization_members')->where('organization_id', $organizationId)->count());

        $this->assertSame(
            self::EXPECTED_FINGERPRINT,
            $this->baselineFingerprint($organizationId),
        );
    }

    private function baselineFingerprint(int $organizationId): string
    {
        $repositories = DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->orderBy('github_repository_id')
            ->get([
                'github_repository_id',
                'name',
                'full_name',
                'visibility',
                'default_branch',
                'sync_enabled',
            ]);

        $pullRequests = DB::table('pull_requests')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->leftJoin('github_users as authors', 'authors.id', '=', 'pull_requests.author_github_user_id')
            ->where('repositories.organization_id', $organizationId)
            ->orderBy('pull_requests.github_pull_request_id')
            ->get([
                'pull_requests.github_pull_request_id',
                'pull_requests.number',
                'pull_requests.state',
                'pull_requests.is_draft',
                'authors.login as author',
                'pull_requests.additions',
                'pull_requests.deletions',
                'pull_requests.created_at_github',
                'pull_requests.closed_at',
                'pull_requests.merged_at',
            ]);

        $reviews = DB::table('pull_request_reviews')
            ->join('pull_requests', 'pull_requests.id', '=', 'pull_request_reviews.pull_request_id')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->leftJoin('github_users as reviewers', 'reviewers.id', '=', 'pull_request_reviews.reviewer_github_user_id')
            ->where('repositories.organization_id', $organizationId)
            ->orderBy('pull_request_reviews.github_review_id')
            ->get([
                'pull_requests.github_pull_request_id',
                'reviewers.login as reviewer',
                'pull_request_reviews.state',
                'pull_request_reviews.submitted_at',
            ]);

        return hash('sha256', json_encode(
            [$repositories, $pullRequests, $reviews],
            JSON_THROW_ON_ERROR,
        ));
    }
}
