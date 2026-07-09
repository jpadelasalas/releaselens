<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseChecklistAndApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.releases', true);
    }

    public function test_an_incomplete_required_checklist_item_blocks_the_released_transition(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = $this->releaseInReview($organizationId, $owner->id);
        $this->approve($organizationId, $release, $manager);
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();

        $item = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/checklist-items",
            ['label' => 'Notify support', 'is_required' => true],
        )->assertCreated()->json('data.id');

        $response = $this->transition($organizationId, $release, $owner, 'released');

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_CHECKLIST_INCOMPLETE');

        $this->actingAs($owner)->patchJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/checklist-items/{$item}",
            ['completed' => true],
        )->assertOk();

        $this->transition($organizationId, $release, $owner, 'released')->assertOk();
    }

    public function test_an_optional_checklist_item_does_not_block_the_released_transition(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = $this->releaseInReview($organizationId, $owner->id);
        $this->approve($organizationId, $release, $manager);
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/checklist-items",
            ['label' => 'Nice to have', 'is_required' => false],
        )->assertCreated();

        $this->transition($organizationId, $release, $owner, 'released')->assertOk();
    }

    public function test_removing_a_checklist_item_unblocks_the_transition(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = $this->releaseInReview($organizationId, $owner->id);
        $this->approve($organizationId, $release, $manager);
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();

        $item = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/checklist-items",
            ['label' => 'Notify support'],
        )->assertCreated()->json('data.id');

        $this->actingAs($owner)->deleteJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/checklist-items/{$item}",
        )->assertOk();

        $this->transition($organizationId, $release, $owner, 'released')->assertOk();
    }

    public function test_a_release_creator_cannot_self_approve_by_default(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = $this->releaseInReview($organizationId, $owner->id);

        $response = $this->approve($organizationId, $release, $owner);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_SELF_APPROVAL_NOT_ALLOWED');
    }

    public function test_self_approval_is_allowed_when_the_policy_permits_it(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = $this->releaseInReview($organizationId, $owner->id);

        app(ReleasePolicyRepositoryInterface::class)->upsertForOrganization($organizationId, [
            'allow_self_approval' => true,
        ]);

        $this->approve($organizationId, $release, $owner)->assertCreated();
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();
    }

    public function test_approval_mode_none_does_not_require_a_recorded_approval(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = $this->releaseInReview($organizationId, $owner->id);

        app(ReleasePolicyRepositoryInterface::class)->upsertForOrganization($organizationId, [
            'approval_mode' => 'none',
        ]);

        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();
    }

    public function test_editing_an_approved_release_reverts_it_to_in_review(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = $this->releaseInReview($organizationId, $owner->id);
        $this->approve($organizationId, $release, $manager);
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();

        $response = $this->actingAs($owner)->patchJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}",
            ['description' => 'Adds a late-breaking fix'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.state', 'in_review');
    }

    public function test_adding_a_pull_request_to_an_approved_release_requires_reapproval(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $release = $this->releaseInReview($organizationId, $owner->id);
        $this->approve($organizationId, $release, $manager);
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();

        $repositoryId = $this->repository($organizationId);
        $pullRequestId = $this->mergedPullRequest($repositoryId);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/pull-requests",
            ['pull_request_id' => $pullRequestId],
        )->assertCreated();

        $response = $this->transition($organizationId, $release, $owner, 'approved');
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'RELEASE_APPROVAL_REQUIRED');

        $this->approve($organizationId, $release, $manager)->assertCreated();
        $this->transition($organizationId, $release, $owner, 'approved')->assertOk();
    }

    public function test_release_policy_can_be_read_and_updated(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');

        $this->actingAs($owner)->getJson("/api/v1/organizations/{$organizationId}/release-policy")
            ->assertOk()
            ->assertJsonPath('data.approval_mode', 'single_approver')
            ->assertJsonPath('data.allow_self_approval', false);

        $response = $this->actingAs($owner)->putJson(
            "/api/v1/organizations/{$organizationId}/release-policy",
            ['approval_mode' => 'none', 'allow_self_approval' => true],
        );

        $response->assertOk();
        $response->assertJsonPath('data.approval_mode', 'none');
        $response->assertJsonPath('data.allow_self_approval', true);
    }

    private function releaseInReview(int $organizationId, int $creatorUserId): int
    {
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, [
            'title' => 'July release',
            'created_by_user_id' => $creatorUserId,
        ]);

        app(ReleaseRepositoryInterface::class)->updateState($release->id, 'in_review');

        return $release->id;
    }

    private function approve(int $organizationId, int $release, User $approver)
    {
        return $this->actingAs($approver)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/approvals",
        );
    }

    private function transition(int $organizationId, int $release, User $actor, string $to)
    {
        return $this->actingAs($actor)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release}/transition",
            ['to' => $to],
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
