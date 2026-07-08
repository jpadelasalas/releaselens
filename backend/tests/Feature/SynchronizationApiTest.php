<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Synchronization\Contracts\GitHubRepositorySyncClientInterface;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Synchronization\Exceptions\SynchronizationException;
use App\Modules\Synchronization\Jobs\SynchronizeRepositoryJob;
use App\Modules\Synchronization\Services\SynchronizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SuccessfulRepositorySyncClient implements GitHubRepositorySyncClientInterface
{
    public function synchronize(int $installationId, string $repositoryFullName, ?string $cursor): array
    {
        return [
            'repository' => [
                'id' => 3001,
                'name' => 'api-renamed',
                'full_name' => 'acme/api-renamed',
                'description' => 'Renamed API repository',
                'visibility' => 'private',
                'default_branch' => 'main',
                'html_url' => 'https://github.com/acme/api-renamed',
                'archived' => false,
            ],
            'items' => [[
                'pull_request' => [
                    'id' => 7001,
                    'number' => 42,
                    'title' => 'Improve synchronization',
                    'html_url' => 'https://github.com/acme/api/pull/42',
                    'state' => 'closed',
                    'draft' => false,
                    'user' => ['id' => 501, 'login' => 'octocat', 'type' => 'User'],
                    'base' => ['ref' => 'main'],
                    'head' => ['ref' => 'sync-improvements'],
                    'additions' => 25,
                    'deletions' => 4,
                    'changed_files' => 3,
                    'commits' => 2,
                    'comments' => 1,
                    'created_at' => '2026-06-25T10:00:00Z',
                    'updated_at' => '2026-06-27T10:00:00Z',
                    'closed_at' => '2026-06-27T10:00:00Z',
                    'merged_at' => '2026-06-27T10:00:00Z',
                ],
                'reviews' => [[
                    'id' => 8001,
                    'state' => 'APPROVED',
                    'submitted_at' => '2026-06-26T10:00:00Z',
                    'user' => ['id' => 502, 'login' => 'reviewer', 'type' => 'User'],
                ]],
            ]],
            'cursor_after' => '2026-06-27T10:00:00Z',
            'rate_limit_remaining' => 4990,
            'rate_limit_reset_at' => '2026-06-27T11:00:00Z',
        ];
    }
}

class InaccessibleRepositorySyncClient implements GitHubRepositorySyncClientInterface
{
    public function synchronize(int $installationId, string $repositoryFullName, ?string $cursor): array
    {
        throw new SynchronizationException(
            'not_found',
            'GitHub repository is no longer accessible.',
        );
    }
}

class MalformedItemRepositorySyncClient implements GitHubRepositorySyncClientInterface
{
    public function synchronize(int $installationId, string $repositoryFullName, ?string $cursor): array
    {
        return [
            'repository' => [],
            'items' => [[
                'pull_request' => ['id' => 0, 'number' => 0, 'title' => 'Missing GitHub id'],
                'reviews' => [],
            ]],
            'cursor_after' => '2026-06-27T10:00:00Z',
            'rate_limit_remaining' => 4990,
            'rate_limit_reset_at' => '2026-06-27T11:00:00Z',
        ];
    }
}

class StalePullRequestRepositorySyncClient implements GitHubRepositorySyncClientInterface
{
    public function synchronize(int $installationId, string $repositoryFullName, ?string $cursor): array
    {
        return [
            'repository' => [],
            'items' => [[
                'pull_request' => [
                    'id' => 9001,
                    'number' => 7,
                    'title' => 'Stale cached title',
                    'html_url' => 'https://github.com/acme/api/pull/7',
                    'state' => 'open',
                    'draft' => false,
                    'user' => ['id' => 601, 'login' => 'author', 'type' => 'User'],
                    'base' => ['ref' => 'main'],
                    'head' => ['ref' => 'feature/reconcile'],
                    'additions' => 1,
                    'deletions' => 1,
                    'changed_files' => 1,
                    'commits' => 1,
                    'comments' => 0,
                    'created_at' => '2026-06-25T00:00:00Z',
                    'updated_at' => '2026-06-25T00:00:00Z',
                    'closed_at' => null,
                    'merged_at' => null,
                ],
                'reviews' => [],
            ]],
            'cursor_after' => '2026-06-25T00:00:00Z',
            'rate_limit_remaining' => 4990,
            'rate_limit_reset_at' => '2026-06-27T11:00:00Z',
        ];
    }
}

class SynchronizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_requests_one_active_sync_run_and_job(): void
    {
        Queue::fake();
        [$manager, $organizationId, $repositoryId] = $this->workspace('manager');

        $first = $this->actingAs($manager)->postJson(
            "/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}/sync",
        );
        $second = $this->actingAs($manager)->postJson(
            "/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}/sync",
        );

        $first->assertAccepted()->assertJsonPath('data.status', 'queued');
        $second->assertAccepted()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('sync_runs', 1);
        Queue::assertPushed(SynchronizeRepositoryJob::class, 1);
    }

    public function test_viewer_cannot_request_sync_but_can_view_safe_history(): void
    {
        Queue::fake();
        [$viewer, $organizationId, $repositoryId] = $this->workspace('viewer');
        DB::table('sync_runs')->insert([
            'repository_id' => $repositoryId,
            'trigger_type' => 'manual',
            'status' => 'success',
            'created_count' => 2,
            'updated_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->postJson("/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}/sync")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->getJson("/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}/sync-runs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_count', 2);
    }

    public function test_sync_job_imports_pull_request_review_and_run_diagnostics(): void
    {
        [$owner, $organizationId, $repositoryId] = $this->workspace('owner');
        $runId = (int) DB::table('sync_runs')->insertGetId([
            'repository_id' => $repositoryId,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $job = new SynchronizeRepositoryJob($runId);
        $job->handle(
            app(SynchronizationRepositoryInterface::class),
            new SuccessfulRepositorySyncClient,
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $runId,
            'status' => 'success',
            'created_count' => 2,
            'rate_limit_remaining' => 4990,
        ]);
        $this->assertDatabaseHas('pull_requests', [
            'repository_id' => $repositoryId,
            'github_pull_request_id' => 7001,
            'number' => 42,
            'additions' => 25,
        ]);
        $this->assertDatabaseHas('pull_request_reviews', [
            'github_review_id' => 8001,
            'state' => 'approved',
        ]);
        $this->assertDatabaseHas('repositories', [
            'id' => $repositoryId,
            'sync_status' => 'success',
            'full_name' => 'acme/api-renamed',
            'is_accessible' => true,
        ]);

        $secondRunId = (int) DB::table('sync_runs')->insertGetId([
            'repository_id' => $repositoryId,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        (new SynchronizeRepositoryJob($secondRunId))->handle(
            app(SynchronizationRepositoryInterface::class),
            new SuccessfulRepositorySyncClient,
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $secondRunId,
            'status' => 'success',
            'created_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 2,
        ]);
        $this->assertDatabaseCount('pull_requests', 1);
        $this->assertDatabaseCount('pull_request_reviews', 1);
        $this->assertDatabaseCount('github_users', 2);
    }

    public function test_permanent_repository_failure_marks_source_inaccessible(): void
    {
        [$owner, $organizationId, $repositoryId] = $this->workspace('owner');
        $runId = (int) DB::table('sync_runs')->insertGetId([
            'repository_id' => $repositoryId,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SynchronizeRepositoryJob($runId))->handle(
            app(SynchronizationRepositoryInterface::class),
            new InaccessibleRepositorySyncClient,
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $runId,
            'status' => 'failed',
            'error_category' => 'not_found',
            'inaccessible_count' => 1,
        ]);
        $this->assertDatabaseHas('repositories', [
            'id' => $repositoryId,
            'is_accessible' => false,
            'access_error' => 'not_found',
        ]);
    }

    public function test_malformed_source_items_are_counted_as_unsupported_not_unchanged(): void
    {
        [, , $repositoryId] = $this->workspace('owner');
        $runId = (int) DB::table('sync_runs')->insertGetId([
            'repository_id' => $repositoryId,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SynchronizeRepositoryJob($runId))->handle(
            app(SynchronizationRepositoryInterface::class),
            new MalformedItemRepositorySyncClient,
        );

        $this->assertDatabaseHas('sync_runs', [
            'id' => $runId,
            'status' => 'success',
            'created_count' => 0,
            'unchanged_count' => 0,
            'unsupported_count' => 1,
        ]);
        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_reconciliation_does_not_regress_a_pull_request_a_webhook_updated_more_recently(): void
    {
        [, , $repositoryId] = $this->workspace('owner');
        $synchronization = app(SynchronizationRepositoryInterface::class);

        $synchronization->upsertPullRequestFromWebhook($repositoryId, [
            'id' => 9001,
            'number' => 7,
            'title' => 'Newer title applied by webhook',
            'html_url' => 'https://github.com/acme/api/pull/7',
            'state' => 'open',
            'draft' => false,
            'user' => ['id' => 601, 'login' => 'author', 'type' => 'User'],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => 'feature/reconcile'],
            'additions' => 5,
            'deletions' => 2,
            'changed_files' => 2,
            'commits' => 3,
            'comments' => 1,
            'created_at' => '2026-06-25T00:00:00Z',
            'updated_at' => '2026-06-27T12:00:00Z',
            'closed_at' => null,
            'merged_at' => null,
        ]);

        $runId = (int) DB::table('sync_runs')->insertGetId([
            'repository_id' => $repositoryId,
            'trigger_type' => 'reconciliation',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reconciliation fetches an older cached snapshot of the same PR.
        (new SynchronizeRepositoryJob($runId))->handle(
            $synchronization,
            new StalePullRequestRepositorySyncClient,
        );

        $this->assertDatabaseHas('pull_requests', [
            'github_pull_request_id' => 9001,
            'title' => 'Newer title applied by webhook',
        ]);
        $this->assertDatabaseHas('sync_runs', [
            'id' => $runId,
            'status' => 'success',
            'unchanged_count' => 1,
            'updated_count' => 0,
        ]);
    }

    public function test_reconcile_enabled_repositories_queues_a_reconciliation_run(): void
    {
        Queue::fake();
        $this->workspace('owner');

        $count = app(SynchronizationService::class)
            ->reconcileEnabledRepositories();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('sync_runs', [
            'trigger_type' => 'reconciliation',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SynchronizeRepositoryJob::class, 1);
    }

    public function test_scheduler_queues_enabled_repositories_once(): void
    {
        Queue::fake();
        $this->workspace('owner');

        $count = app(SynchronizationService::class)
            ->scheduleEnabledRepositories();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('sync_runs', [
            'trigger_type' => 'scheduled',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SynchronizeRepositoryJob::class, 1);
    }

    /** @return array{User, int, int} */
    private function workspace(string $role): array
    {
        $user = User::query()->create([
            'name' => 'Sync User',
            'email' => "{$role}@example.com",
            'normalized_email' => "{$role}@example.com",
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => "acme-{$role}",
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $installationId = (int) DB::table('github_installations')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => random_int(10000, 99999),
            'github_account_login' => 'acme',
            'permissions' => json_encode(['pull_requests' => 'read']),
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repositoryId = (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => $installationId,
            'github_repository_id' => random_int(100000, 999999),
            'name' => 'api',
            'full_name' => 'acme/api',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'never_synced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $organizationId, $repositoryId];
    }
}
