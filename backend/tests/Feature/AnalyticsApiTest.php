<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private int $githubPullRequestId = 30_000_000;

    public function test_demo_context_can_read_active_organization_analytics(): void
    {
        $organizationId = $this->organization('Northstar Engineering', 'northstar-engineering');
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('author');

        $this->pullRequest($repositoryId, $authorId, 1, '2026-06-19T06:00:00Z');

        $response = $this
            ->withSession([
                'releaselens.context' => [
                    'type' => 'demo',
                    'session_id' => 'demo-session-id',
                    'organization_id' => $organizationId,
                    'organization_slug' => 'northstar-engineering',
                ],
            ])
            ->getJson("/api/v1/organizations/{$organizationId}/analytics/summary");

        $response
            ->assertOk()
            ->assertJsonPath('data.selected_repository_count', 1)
            ->assertJsonPath('data.metrics.waiting_for_first_review', 1)
            ->assertJsonPath(
                'data.applied_filters.date_from',
                '2026-05-21T00:00:00+00:00',
            )
            ->assertJsonPath(
                'data.applied_filters.date_to',
                '2026-06-19T23:59:59+00:00',
            );
    }

    public function test_demo_context_cannot_read_another_organization_analytics(): void
    {
        $demoOrganizationId = $this->organization('Northstar Engineering', 'northstar-engineering');
        $otherOrganizationId = $this->organization('Private Engineering', 'private-engineering');

        $response = $this
            ->withSession([
                'releaselens.context' => [
                    'type' => 'demo',
                    'session_id' => 'demo-session-id',
                    'organization_id' => $demoOrganizationId,
                    'organization_slug' => 'northstar-engineering',
                ],
            ])
            ->getJson("/api/v1/organizations/{$otherOrganizationId}/analytics/summary");

        $response->assertForbidden();
    }

    public function test_connected_member_reads_only_their_current_workspace_analytics(): void
    {
        CarbonImmutable::setTestNow('2026-06-28T12:00:00Z');
        $user = $this->user('member@example.com');
        $organizationId = $this->organization('Connected Engineering', 'connected-engineering');
        $otherOrganizationId = $this->organization('Other Engineering', 'other-engineering');
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'viewer',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $repositoryId = $this->repository($organizationId);
        $authorId = $this->githubUser('connected-author');
        $this->pullRequest($repositoryId, $authorId, 1, '2026-06-27T06:00:00Z');

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organizationId}/analytics/summary")
            ->assertOk()
            ->assertJsonPath('data.metrics.waiting_for_first_review', 1)
            ->assertJsonPath(
                'data.applied_filters.date_to',
                '2026-06-28T23:59:59+00:00',
            );

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organizationId}/analytics/attention")
            ->assertOk()
            ->assertJsonPath('data.records.0.age_hours', 30);

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$otherOrganizationId}/analytics/summary")
            ->assertForbidden();

        CarbonImmutable::setTestNow();
    }

    private function organization(string $name, string $slug): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'UTC',
            'is_demo' => $slug === 'northstar-engineering',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Analytics User',
            'email' => $email,
            'normalized_email' => $email,
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }

    private function repository(int $organizationId): int
    {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => random_int(10_000_000, 99_999_999),
            'name' => 'customer-portal',
            'full_name' => 'northstar/customer-portal',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'last_successful_sync_at' => '2026-06-19T12:00:00Z',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function githubUser(string $login): int
    {
        return (int) DB::table('github_users')->insertGetId([
            'github_user_id' => random_int(10_000_000, 99_999_999),
            'login' => $login,
            'type' => 'User',
            'is_bot' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pullRequest(
        int $repositoryId,
        int $authorId,
        int $number,
        string $createdAt,
    ): int {
        $createdAt = CarbonImmutable::parse($createdAt)->utc();

        return (int) DB::table('pull_requests')->insertGetId([
            'repository_id' => $repositoryId,
            'github_pull_request_id' => $this->githubPullRequestId++,
            'number' => $number,
            'title' => "PR {$number}",
            'state' => 'open',
            'is_draft' => false,
            'author_github_user_id' => $authorId,
            'base_ref' => 'main',
            'head_ref' => "feature/{$number}",
            'additions' => 100,
            'deletions' => 0,
            'changed_files' => 1,
            'commits_count' => 1,
            'comments_count' => 0,
            'created_at_github' => $createdAt,
            'updated_at_github' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
