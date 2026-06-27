<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Synchronization\Contracts\GitHubRepositorySyncClientInterface;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
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
        ]);
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
