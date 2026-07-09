<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseCrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.releases', true);
    }

    public function test_an_owner_can_create_a_release(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases",
            ['title' => 'July release'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.state', 'draft');
        $response->assertJsonPath('data.title', 'July release');
    }

    public function test_a_viewer_cannot_create_a_release(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');

        $response = $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/releases",
            ['title' => 'July release'],
        );

        $response->assertForbidden();
    }

    public function test_list_filters_releases_by_state(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $releases = app(ReleaseRepositoryInterface::class);
        $releases->create($organizationId, ['title' => 'Draft release']);
        $inReview = $releases->create($organizationId, ['title' => 'In review release']);
        $releases->updateState($inReview->id, 'in_review');

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/releases?state=in_review",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.title', 'In review release');
    }

    public function test_a_non_member_cannot_list_another_organizations_releases(): void
    {
        $organizationId = $this->organization();
        $outsider = $this->member($this->organization(), 'owner');

        $response = $this->actingAs($outsider)->getJson(
            "/api/v1/organizations/{$organizationId}/releases",
        );

        $response->assertForbidden();
    }

    public function test_show_includes_pull_requests_and_repositories(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $pullRequestId = $this->mergedPullRequest($repositoryId);
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $pullRequestId],
        )->assertCreated();

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.pull_requests'));
        $this->assertCount(1, $response->json('data.repositories'));
    }

    public function test_update_edits_the_title_and_description(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'Old title']);

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}",
            ['title' => 'New title', 'description' => 'Updated description'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
        $response->assertJsonPath('data.description', 'Updated description');
    }

    public function test_a_closed_release_cannot_be_edited(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $releases = app(ReleaseRepositoryInterface::class);
        $release = $releases->create($organizationId, ['title' => 'July release']);
        $releases->updateState($release->id, 'closed');

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}",
            ['title' => 'New title'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_NOT_EDITABLE');
    }

    public function test_transition_moves_a_release_through_the_allowed_states(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, [
            'title' => 'July release',
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/transition",
            ['to' => 'in_review'],
        )->assertOk()->assertJsonPath('data.state', 'in_review');

        $this->actingAs($manager)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/approvals",
        )->assertCreated();

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/transition",
            ['to' => 'approved'],
        )->assertOk()->assertJsonPath('data.state', 'approved');

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/transition",
            ['to' => 'released'],
        );
        $response->assertOk()->assertJsonPath('data.state', 'released');
        $this->assertNotNull($response->json('data.released_at'));
    }

    public function test_transition_rejects_a_disallowed_jump(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/transition",
            ['to' => 'released'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_INVALID_TRANSITION');
    }

    public function test_records_a_state_changed_activity_on_transition(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/transition",
            ['to' => 'in_review'],
        )->assertOk();

        $this->assertSame(
            1,
            DB::table('release_activities')->where('release_id', $release->id)->count(),
        );
        $this->assertSame(
            'state_changed',
            DB::table('release_activities')
                ->where('release_id', $release->id)
                ->orderByDesc('id')
                ->value('action'),
        );
    }

    public function test_only_a_merged_pull_request_from_the_same_organization_can_be_added(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $otherOrganizationId = $this->organization();
        $otherRepositoryId = $this->repository($otherOrganizationId);
        $otherOrgPullRequestId = $this->mergedPullRequest($otherRepositoryId);

        $repositoryId = $this->repository($organizationId);
        $openPullRequestId = $this->openPullRequest($repositoryId);

        $crossTenant = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $otherOrgPullRequestId],
        );
        $crossTenant->assertStatus(422);
        $crossTenant->assertJsonPath('error.code', 'RELEASE_PULL_REQUEST_NOT_ELIGIBLE');

        $unmerged = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $openPullRequestId],
        );
        $unmerged->assertStatus(422);
        $unmerged->assertJsonPath('error.code', 'RELEASE_PULL_REQUEST_NOT_ELIGIBLE');
    }

    public function test_adding_the_same_pull_request_twice_is_rejected(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        $repositoryId = $this->repository($organizationId);
        $pullRequestId = $this->mergedPullRequest($repositoryId);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $pullRequestId],
        )->assertCreated();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $pullRequestId],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_PULL_REQUEST_ALREADY_INCLUDED');
    }

    public function test_removing_a_pull_request_deletes_the_link(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        $repositoryId = $this->repository($organizationId);
        $pullRequestId = $this->mergedPullRequest($repositoryId);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests",
            ['pull_request_id' => $pullRequestId],
        )->assertCreated();

        $response = $this->actingAs($owner)->deleteJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/pull-requests/{$pullRequestId}",
        );

        $response->assertOk();
        $this->assertSame(
            0,
            DB::table('release_pull_requests')->where('release_id', $release->id)->count(),
        );
    }

    private function organization(): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(int $organizationId): int
    {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => random_int(1_000_000, 9_999_999),
            'name' => 'service',
            'full_name' => 'acme/service-'.uniqid('', true),
            'visibility' => 'public',
            'sync_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mergedPullRequest(int $repositoryId): int
    {
        return (int) DB::table('pull_requests')->insertGetId([
            'repository_id' => $repositoryId,
            'github_pull_request_id' => random_int(1_000_000, 9_999_999),
            'number' => random_int(1, 9_999),
            'title' => 'Add feature',
            'state' => 'closed',
            'is_draft' => false,
            'base_ref' => 'main',
            'head_ref' => 'feature',
            'created_at_github' => now()->subDays(2),
            'merged_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function openPullRequest(int $repositoryId): int
    {
        return (int) DB::table('pull_requests')->insertGetId([
            'repository_id' => $repositoryId,
            'github_pull_request_id' => random_int(1_000_000, 9_999_999),
            'number' => random_int(1, 9_999),
            'title' => 'Work in progress',
            'state' => 'open',
            'is_draft' => false,
            'base_ref' => 'main',
            'head_ref' => 'wip',
            'created_at_github' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function member(int $organizationId, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid('', true).'@example.com',
            'normalized_email' => $role.'-'.uniqid('', true).'@example.com',
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);

        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
