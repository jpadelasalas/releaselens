<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeploymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.deployments', true);
    }

    public function test_list_returns_deployments_filtered_by_status(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $this->deployment($organizationId, $repositoryId, 900001, 'success');
        $this->deployment($organizationId, $repositoryId, 900002, 'failure');

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/deployments?status=failure",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.status', 'failure');
    }

    public function test_a_non_member_cannot_list_another_organizations_deployments(): void
    {
        $organizationId = $this->organization();
        $outsider = $this->member($this->organization(), 'owner');

        $response = $this->actingAs($outsider)->getJson(
            "/api/v1/organizations/{$organizationId}/deployments",
        );

        $response->assertForbidden();
    }

    public function test_show_includes_status_events(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $deploymentId = $this->deployment($organizationId, $repositoryId, 900003, 'success');
        app(DeploymentRepositoryInterface::class)->recordStatusEvent($deploymentId, [
            'status' => 'success',
            'original_status' => 'success',
            'description' => 'Deployed',
            'log_url' => null,
            'environment_url' => null,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/deployments/{$deploymentId}",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.status_events'));
    }

    public function test_an_owner_can_link_a_deployment_to_a_release_in_the_same_organization(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $deploymentId = $this->deployment($organizationId, $repositoryId, 900004, 'success');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/deployments/{$deploymentId}/link-release",
            ['release_id' => $release->id],
        );

        $response->assertOk();
        $this->assertSame($release->id, DB::table('deployments')->where('id', $deploymentId)->value('release_id'));
    }

    public function test_linking_a_release_from_another_organization_is_rejected(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $deploymentId = $this->deployment($organizationId, $repositoryId, 900005, 'success');

        $otherOrganizationId = $this->organization();
        $otherRelease = app(ReleaseRepositoryInterface::class)->create($otherOrganizationId, ['title' => 'Other release']);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/deployments/{$deploymentId}/link-release",
            ['release_id' => $otherRelease->id],
        );

        $response->assertNotFound();
    }

    public function test_a_viewer_cannot_link_a_release(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        $repositoryId = $this->repository($organizationId);
        $deploymentId = $this->deployment($organizationId, $repositoryId, 900006, 'success');

        $response = $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/deployments/{$deploymentId}/link-release",
            ['release_id' => null],
        );

        $response->assertForbidden();
    }

    public function test_an_owner_can_create_and_list_environment_mappings(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/environment-mappings",
            ['source_environment' => 'prod-us', 'normalized_environment' => 'production', 'is_production' => true],
        )->assertCreated();

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/environment-mappings",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.is_production', true);
    }

    public function test_release_detail_includes_linked_deployments_when_the_flag_is_enabled(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $repositoryId = $this->repository($organizationId);
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        $deploymentId = $this->deployment($organizationId, $repositoryId, 900007, 'success');
        app(DeploymentRepositoryInterface::class)->linkRelease($deploymentId, $release->id);

        config()->set('releaselens.features.releases', true);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data.deployments'));
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

    private function deployment(int $organizationId, int $repositoryId, int $githubDeploymentId, string $status): int
    {
        return app(DeploymentRepositoryInterface::class)->upsertFromWebhook($organizationId, $repositoryId, [
            'github_deployment_id' => $githubDeploymentId,
            'ref' => 'main',
            'sha' => 'abc123',
            'original_environment' => 'production',
            'normalized_environment' => 'production',
            'is_production' => true,
            'status' => $status,
            'original_status' => $status,
            'created_at_github' => now(),
        ])->id;
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
