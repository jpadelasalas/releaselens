<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class FakeGitHubAppClient implements GitHubAppClientInterface
{
    public function installationRepositories(int $installationId): array
    {
        return [];
    }
}

class GitHubConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'releaselens.client_url' => 'http://frontend.test',
            'releaselens.github.app_slug' => 'release-lens-test',
            'releaselens.github.state_ttl_minutes' => 10,
        ]);

        $this->app->instance(
            GitHubAppClientInterface::class,
            new class extends FakeGitHubAppClient
            {
                public function installation(int $installationId): array
                {
                    return [
                        'id' => $installationId,
                        'account' => [
                            'id' => 987,
                            'login' => 'acme-engineering',
                            'type' => 'Organization',
                        ],
                        'repository_selection' => 'selected',
                        'permissions' => [
                            'metadata' => 'read',
                            'pull_requests' => 'read',
                        ],
                        'suspended_at' => null,
                    ];
                }
            },
        );
    }

    public function test_owner_and_manager_can_start_connection_but_viewer_cannot(): void
    {
        $owner = $this->user('owner@example.com');
        $manager = $this->user('manager@example.com');
        $viewer = $this->user('viewer@example.com');
        $outsider = $this->user('outsider@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $this->membership($organizationId, $manager->id, 'manager');
        $this->membership($organizationId, $viewer->id, 'viewer');

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertOk()
            ->assertJsonPath(
                'data.url',
                fn (string $url): bool => str_starts_with(
                    $url,
                    'https://github.com/apps/release-lens-test/installations/new?state=',
                ),
            );

        $this->actingAs($manager)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertOk();
        $this->actingAs($viewer)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertForbidden();
        $this->actingAs($outsider)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertNotFound();
    }

    public function test_callback_persists_verified_metadata_and_rejects_state_replay(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $start = $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertOk();
        parse_str(
            (string) parse_url($start->json('data.url'), PHP_URL_QUERY),
            $query,
        );

        $callbackUrl = '/api/v1/github/callback?'.http_build_query([
            'installation_id' => 123456,
            'setup_action' => 'install',
            'state' => $query['state'],
        ]);

        $this->get($callbackUrl)
            ->assertRedirect('http://frontend.test/app?github=connected');
        $this->assertDatabaseHas('github_installations', [
            'organization_id' => $organizationId,
            'github_installation_id' => 123456,
            'github_account_login' => 'acme-engineering',
            'github_account_type' => 'Organization',
            'repository_selection' => 'selected',
            'disconnected_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organizationId,
            'event_type' => 'github.connected',
        ]);

        $this->get($callbackUrl)
            ->assertRedirect(
                'http://frontend.test/app?github=github_state_invalid',
            );
        $this->assertDatabaseCount('github_installations', 1);
    }

    public function test_invalid_callback_state_creates_no_connection(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect")
            ->assertOk();

        $this->get('/api/v1/github/callback?installation_id=123&state=wrong')
            ->assertRedirect(
                'http://frontend.test/app?github=github_state_invalid',
            );
        $this->assertDatabaseCount('github_installations', 0);
    }

    public function test_callback_rejects_installations_with_write_permissions(): void
    {
        $this->app->instance(
            GitHubAppClientInterface::class,
            new class extends FakeGitHubAppClient
            {
                public function installation(int $installationId): array
                {
                    return [
                        'id' => $installationId,
                        'account' => ['id' => 987, 'login' => 'acme', 'type' => 'Organization'],
                        'repository_selection' => 'selected',
                        'permissions' => ['pull_requests' => 'write'],
                    ];
                }
            },
        );
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $start = $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organizationId}/github/connect");
        parse_str(
            (string) parse_url($start->json('data.url'), PHP_URL_QUERY),
            $query,
        );

        $this->get('/api/v1/github/callback?'.http_build_query([
            'installation_id' => 123,
            'state' => $query['state'],
        ]))->assertRedirect(
            'http://frontend.test/app?github=github_permissions_invalid',
        );
        $this->assertDatabaseCount('github_installations', 0);
    }

    public function test_members_can_view_status_and_only_owner_can_disconnect(): void
    {
        $owner = $this->user('owner@example.com');
        $manager = $this->user('manager@example.com');
        $viewer = $this->user('viewer@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $this->membership($organizationId, $manager->id, 'manager');
        $this->membership($organizationId, $viewer->id, 'viewer');
        $installationId = $this->installation($organizationId);
        $this->repository($organizationId, $installationId);

        $this->actingAs($viewer)
            ->getJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.account.login', 'acme-engineering')
            ->assertJsonMissingPath('data.github_installation_id');

        $this->actingAs($manager)
            ->deleteJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertOk()
            ->assertJsonPath('data.disconnected', true);
        $this->assertDatabaseMissing('repositories', [
            'organization_id' => $organizationId,
            'sync_enabled' => true,
        ]);
        $this->assertDatabaseHas('repositories', [
            'organization_id' => $organizationId,
            'full_name' => 'acme/release-lens',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organizationId,
            'event_type' => 'github.disconnected',
        ]);
    }

    public function test_status_refreshes_repository_selection_and_suspension_from_github(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $installationId = $this->installation($organizationId);
        DB::table('github_installations')->where('id', $installationId)->update([
            'repository_selection' => 'all',
        ]);
        $this->app->instance(
            GitHubAppClientInterface::class,
            new class extends FakeGitHubAppClient
            {
                public function installation(int $installationId): array
                {
                    return [
                        'id' => $installationId,
                        'account' => ['id' => 987, 'login' => 'acme-engineering', 'type' => 'Organization'],
                        'repository_selection' => 'selected',
                        'permissions' => ['metadata' => 'read', 'pull_requests' => 'read'],
                        'suspended_at' => '2026-06-27T14:00:00Z',
                    ];
                }
            },
        );

        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertOk()
            ->assertJsonPath('data.status', 'action_required')
            ->assertJsonPath('data.repository_selection', 'selected')
            ->assertJsonPath('data.verification_status', 'verified');
        $this->assertDatabaseHas('github_installations', [
            'id' => $installationId,
            'repository_selection' => 'selected',
        ]);
    }

    public function test_status_marks_a_remotely_removed_installation_disconnected(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $installationId = $this->installation($organizationId);
        $this->repository($organizationId, $installationId);
        $this->app->instance(
            GitHubAppClientInterface::class,
            new class extends FakeGitHubAppClient
            {
                public function installation(int $installationId): array
                {
                    throw new GitHubConnectionException(
                        'GITHUB_INSTALLATION_NOT_FOUND',
                        'The GitHub installation no longer exists.',
                        404,
                    );
                }
            },
        );

        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertOk()
            ->assertJsonPath('data.status', 'disconnected')
            ->assertJsonPath('data.verification_status', 'verified');
        $this->assertDatabaseMissing('repositories', [
            'organization_id' => $organizationId,
            'sync_enabled' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organizationId,
            'event_type' => 'github.disconnected_remotely',
        ]);
    }

    public function test_temporary_github_failure_does_not_disconnect_installation(): void
    {
        $owner = $this->user('owner@example.com');
        $organizationId = $this->organization();
        $this->membership($organizationId, $owner->id, 'owner');
        $installationId = $this->installation($organizationId);
        $this->app->instance(
            GitHubAppClientInterface::class,
            new class extends FakeGitHubAppClient
            {
                public function installation(int $installationId): array
                {
                    throw new GitHubConnectionException(
                        'GITHUB_API_UNAVAILABLE',
                        'GitHub could not be reached.',
                        503,
                    );
                }
            },
        );

        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organizationId}/github/connection")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.verification_status', 'unavailable');
        $this->assertDatabaseHas('github_installations', [
            'id' => $installationId,
            'disconnected_at' => null,
        ]);
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Workspace User',
            'email' => $email,
            'normalized_email' => $email,
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }

    private function organization(): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Platform Team',
            'slug' => 'platform-team',
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

    private function installation(int $organizationId): int
    {
        return (int) DB::table('github_installations')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => 123456,
            'github_account_id' => 987,
            'github_account_login' => 'acme-engineering',
            'github_account_type' => 'Organization',
            'repository_selection' => 'selected',
            'permissions' => json_encode(['pull_requests' => 'read']),
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(int $organizationId, int $installationId): void
    {
        DB::table('repositories')->insert([
            'organization_id' => $organizationId,
            'github_installation_id' => $installationId,
            'github_repository_id' => 555,
            'name' => 'release-lens',
            'full_name' => 'acme/release-lens',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'never_synced',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
