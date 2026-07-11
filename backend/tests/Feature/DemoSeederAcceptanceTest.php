<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeederAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_anchor_and_random_seed_reproduce_the_same_dataset(): void
    {
        config()->set('releaselens.demo.anchor_date', '2026-06-19T12:00:00Z');
        config()->set('releaselens.demo.random_seed', 1001);

        $this->seed(DemoSeeder::class);
        $firstFingerprint = $this->datasetFingerprint();

        $this->seed(DemoSeeder::class);
        $secondFingerprint = $this->datasetFingerprint();

        $this->assertSame($firstFingerprint, $secondFingerprint);
        $this->assertSame(192, DB::table('pull_requests')->count());
    }

    public function test_demo_seed_contains_only_bounded_synthetic_identifiers(): void
    {
        $this->seed(DemoSeeder::class);

        $organization = DB::table('organizations')
            ->where('is_demo', true)
            ->first();

        $this->assertNotNull($organization);
        $this->assertSame('Northstar Engineering', $organization->name);
        $this->assertSame('northstar-engineering', $organization->slug);

        $repositories = DB::table('repositories')
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();

        $this->assertSame(
            ['billing-api', 'customer-portal', 'developer-tools', 'mobile-shell'],
            $repositories->pluck('name')->all(),
        );
        $this->assertTrue($repositories->every(
            fn (object $repository): bool => $repository->github_installation_id === null &&
                $repository->html_url === null &&
                str_starts_with($repository->full_name, 'northstar-engineering/')
        ));
        $this->assertSame(
            0,
            DB::table('pull_requests')->whereNotNull('html_url')->count(),
        );
        $this->assertSame(
            0,
            DB::table('users')
                ->where('email', 'not like', '%@releaselens.invalid')
                ->count(),
        );
    }

    public function test_demo_seed_includes_v2_webhook_delivery_and_reconciliation_scenarios(): void
    {
        $this->seed(DemoSeeder::class);

        $recovered = DB::table('webhook_deliveries')
            ->where('github_delivery_id', 'demo-delivery-recovered-0001')
            ->first();
        $this->assertNotNull($recovered);
        $this->assertSame('processed', $recovered->status);
        $this->assertSame(
            2,
            DB::table('webhook_processing_attempts')
                ->where('webhook_delivery_id', $recovered->id)
                ->count(),
        );
        $this->assertSame(
            'failed',
            DB::table('webhook_processing_attempts')
                ->where('webhook_delivery_id', $recovered->id)
                ->where('attempt_number', 1)
                ->value('status'),
        );
        $this->assertSame(
            'succeeded',
            DB::table('webhook_processing_attempts')
                ->where('webhook_delivery_id', $recovered->id)
                ->where('attempt_number', 2)
                ->value('status'),
        );

        $deadLettered = DB::table('webhook_deliveries')
            ->where('github_delivery_id', 'demo-delivery-dead-lettered-0001')
            ->first();
        $this->assertNotNull($deadLettered);
        $this->assertSame('dead_lettered', $deadLettered->status);

        $processed = DB::table('webhook_deliveries')
            ->where('github_delivery_id', 'demo-delivery-processed-0001')
            ->first();
        $this->assertNotNull($processed);
        $this->assertSame('processed', $processed->status);

        $this->assertSame(
            1,
            DB::table('sync_runs')
                ->where('trigger_type', 'reconciliation')
                ->where('updated_count', 2)
                ->count(),
        );
    }

    public function test_reseeding_the_demo_does_not_duplicate_or_fail_on_webhook_deliveries(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(
            1,
            DB::table('webhook_deliveries')
                ->where('github_delivery_id', 'demo-delivery-recovered-0001')
                ->count(),
        );
    }

    public function test_demo_seed_includes_v2_1_release_and_deployment_scenarios(): void
    {
        $this->seed(DemoSeeder::class);

        $released = DB::table('releases')->where('state', 'released')->first();
        $this->assertNotNull($released);
        $this->assertNotNull($released->released_at);
        $this->assertGreaterThan(
            0,
            DB::table('release_pull_requests')->where('release_id', $released->id)->count(),
        );
        $this->assertSame(
            1,
            DB::table('release_approvals')->where('release_id', $released->id)->count(),
        );

        $inReview = DB::table('releases')->where('state', 'in_review')->first();
        $this->assertNotNull($inReview);
        $this->assertSame(
            0,
            DB::table('release_approvals')->where('release_id', $inReview->id)->count(),
        );

        $failedDeployment = DB::table('deployments')
            ->where('github_deployment_id', 9_300_000_001)
            ->first();
        $this->assertNotNull($failedDeployment);
        $this->assertSame('failure', $failedDeployment->status);
        $this->assertNull($failedDeployment->release_id);

        $rolledBackDeployment = DB::table('deployments')
            ->where('github_deployment_id', 9_300_000_002)
            ->first();
        $this->assertNotNull($rolledBackDeployment);
        $this->assertSame('inactive', $rolledBackDeployment->status);
        $this->assertSame(
            ['success', 'inactive'],
            DB::table('deployment_status_events')
                ->where('deployment_id', $rolledBackDeployment->id)
                ->orderBy('occurred_at')
                ->pluck('status')
                ->all(),
        );
    }

    public function test_reseeding_the_demo_does_not_duplicate_releases_or_deployments(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, DB::table('releases')->count());
        $this->assertSame(
            1,
            DB::table('deployments')->where('github_deployment_id', 9_300_000_001)->count(),
        );
        $this->assertSame(
            1,
            DB::table('deployments')->where('github_deployment_id', 9_300_000_002)->count(),
        );
    }

    private function datasetFingerprint(): string
    {
        $repositories = DB::table('repositories')
            ->join('organizations', 'organizations.id', '=', 'repositories.organization_id')
            ->where('organizations.slug', 'northstar-engineering')
            ->orderBy('repositories.github_repository_id')
            ->get([
                'repositories.github_repository_id',
                'repositories.name',
                'repositories.full_name',
                'repositories.last_successful_sync_at',
            ]);

        $pullRequests = DB::table('pull_requests')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->leftJoin('github_users as authors', 'authors.id', '=', 'pull_requests.author_github_user_id')
            ->whereIn('repositories.github_repository_id', $repositories->pluck('github_repository_id'))
            ->orderBy('pull_requests.github_pull_request_id')
            ->get([
                'repositories.github_repository_id',
                'pull_requests.github_pull_request_id',
                'pull_requests.number',
                'pull_requests.title',
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
            ->join('github_users as reviewers', 'reviewers.id', '=', 'pull_request_reviews.reviewer_github_user_id')
            ->whereIn('pull_requests.github_pull_request_id', $pullRequests->pluck('github_pull_request_id'))
            ->orderBy('pull_request_reviews.github_review_id')
            ->get([
                'pull_requests.github_pull_request_id',
                'pull_request_reviews.github_review_id',
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
