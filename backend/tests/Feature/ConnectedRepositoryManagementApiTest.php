<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RepositoryDiscoveryGitHubClient implements GitHubAppClientInterface
{
    public function installationAccessToken(int $installationId): string
    {
        return 'test-installation-token';
    }

    public function installation(int $installationId): array
    {
        return [
            'id' => $installationId,
            'account' => ['id' => 10, 'login' => 'acme', 'type' => 'Organization'],
            'repository_selection' => 'selected',
            'permissions' => ['metadata' => 'read', 'pull_requests' => 'read'],
            'suspended_at' => null,
        ];
    }

    public function installationRepositories(int $installationId): array
    {
        return [
            $this->repository(101, 'api', false),
            $this->repository(102, 'web', true),
            $this->repository(103, 'worker', false),
        ];
    }

    /** @return array<string, mixed> */
    private function repository(int $id, string $name, bool $archived): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'full_name' => "acme/{$name}",
            'description' => "The {$name} repository",
            'visibility' => 'private',
            'default_branch' => 'main',
            'html_url' => "https://github.com/acme/{$name}",
            'archived' => $archived,
        ];
    }
}

class ConnectedRepositoryManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(
            GitHubAppClientInterface::class,
            new RepositoryDiscoveryGitHubClient,
        );
    }

    public function test_owner_discovers_and_imports_selected_repositories_idempotently(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization('Acme', 'acme');
        $this->membership($organizationId, $owner->id, 'owner');
        $this->installation($organizationId);

        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organizationId}/github/available-repositories")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.full_name', 'acme/api')
            ->assertJsonPath('data.1.is_archived', true)
            ->assertJsonPath('data.0.is_monitored', false);

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/repositories/import", [
                'repository_ids' => [101, 102],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.default_branch', 'main')
            ->assertJsonPath('data.0.sync_enabled', true);

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/repositories/import", [
                'repository_ids' => [102],
            ])
            ->assertOk();

        $this->assertDatabaseCount('repositories', 2);
        $this->assertDatabaseHas('repositories', [
            'organization_id' => $organizationId,
            'github_repository_id' => 101,
            'sync_enabled' => false,
        ]);
        $this->assertDatabaseHas('repositories', [
            'organization_id' => $organizationId,
            'github_repository_id' => 102,
            'sync_enabled' => true,
            'is_archived' => true,
        ]);
    }

    public function test_manager_can_toggle_monitoring_and_connected_member_can_list(): void
    {
        $manager = $this->user('manager@example.com');
        $viewer = $this->user('viewer@example.com');
        $organizationId = $this->organization('Acme', 'acme');
        $this->membership($organizationId, $manager->id, 'manager');
        $this->membership($organizationId, $viewer->id, 'viewer');
        $installationId = $this->installation($organizationId);
        $repositoryId = $this->storedRepository($organizationId, $installationId, 101);

        $this->actingAs($manager)
            ->patchJson(
                "/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}",
                ['sync_enabled' => false],
            )
            ->assertOk()
            ->assertJsonPath('data.sync_enabled', false);

        $this->actingAs($viewer)
            ->getJson("/api/v1/organizations/{$organizationId}/repositories")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.github_repository_id', 101);
    }

    public function test_viewer_cannot_discover_import_or_update_repositories(): void
    {
        $viewer = $this->user('viewer@example.com');
        $organizationId = $this->organization('Acme', 'acme');
        $this->membership($organizationId, $viewer->id, 'viewer');
        $installationId = $this->installation($organizationId);
        $repositoryId = $this->storedRepository($organizationId, $installationId, 101);

        $this->actingAs($viewer)
            ->getJson("/api/v1/organizations/{$organizationId}/github/available-repositories")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson("/api/v1/organizations/{$organizationId}/repositories/import", [
                'repository_ids' => [101],
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->patchJson(
                "/api/v1/organizations/{$organizationId}/repositories/{$repositoryId}",
                ['sync_enabled' => false],
            )
            ->assertForbidden();
    }

    public function test_cross_tenant_repository_update_is_hidden_and_selection_is_validated(): void
    {
        $owner = $this->user('owner@example.com');
        $firstOrganizationId = $this->organization('First', 'first');
        $secondOrganizationId = $this->organization('Second', 'second');
        $this->membership($firstOrganizationId, $owner->id, 'owner');
        $this->membership($secondOrganizationId, $owner->id, 'owner');
        $firstInstallationId = $this->installation($firstOrganizationId, 9001);
        $this->installation($secondOrganizationId, 9002);
        $firstRepositoryId = $this->storedRepository(
            $firstOrganizationId,
            $firstInstallationId,
            101,
        );

        $this->actingAs($owner)
            ->patchJson(
                "/api/v1/organizations/{$secondOrganizationId}/repositories/{$firstRepositoryId}",
                ['sync_enabled' => false],
            )
            ->assertNotFound();
        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$secondOrganizationId}/repositories/import", [
                'repository_ids' => [999999],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'REPOSITORY_NOT_AVAILABLE');
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Repository User',
            'email' => $email,
            'normalized_email' => $email,
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }

    private function organization(string $name, string $slug): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function membership(int $organizationId, int $userId, string $role): void
    {
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function installation(int $organizationId, int $githubId = 9001): int
    {
        return (int) DB::table('github_installations')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => $githubId,
            'github_account_login' => 'acme',
            'repository_selection' => 'selected',
            'permissions' => json_encode(['pull_requests' => 'read']),
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function storedRepository(
        int $organizationId,
        int $installationId,
        int $githubId,
    ): int {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => $installationId,
            'github_repository_id' => $githubId,
            'name' => 'api',
            'full_name' => 'acme/api',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'never_synced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
