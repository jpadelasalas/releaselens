<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_creates_workspace_as_owner_with_unique_slug(): void
    {
        $user = $this->user('owner@example.com');

        $first = $this->actingAs($user)->postJson('/api/v1/organizations', [
            'name' => 'Platform Team',
            'timezone' => 'Asia/Manila',
        ]);
        $second = $this->actingAs($user)->postJson('/api/v1/organizations', [
            'name' => 'Platform Team',
            'timezone' => 'UTC',
        ]);

        $first
            ->assertCreated()
            ->assertJsonPath('data.memberships.0.organization.slug', 'platform-team')
            ->assertJsonPath('data.memberships.0.role', 'owner');
        $second
            ->assertCreated()
            ->assertJsonPath('data.memberships.1.organization.slug', 'platform-team-2');

        $secondOrganizationId = (int) DB::table('organizations')
            ->where('slug', 'platform-team-2')
            ->value('id');

        $second->assertJsonPath(
            'data.active_organization_id',
            $secondOrganizationId,
        );
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $secondOrganizationId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_lists_only_their_workspace_memberships(): void
    {
        $user = $this->user('viewer@example.com');
        $otherUser = $this->user('other@example.com');
        $visibleOrganizationId = $this->organization('Visible Team', 'visible-team');
        $hiddenOrganizationId = $this->organization('Hidden Team', 'hidden-team');
        $this->membership($visibleOrganizationId, $user->id, 'viewer');
        $this->membership($hiddenOrganizationId, $otherUser->id, 'owner');

        $this->actingAs($user)
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.organization.id', $visibleOrganizationId)
            ->assertJsonMissing(['slug' => 'hidden-team']);
    }

    public function test_member_can_activate_workspace_but_non_member_receives_not_found(): void
    {
        $user = $this->user('member@example.com');
        $memberOrganizationId = $this->organization('Member Team', 'member-team');
        $otherOrganizationId = $this->organization('Other Team', 'other-team');
        $this->membership($memberOrganizationId, $user->id, 'manager');

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$memberOrganizationId}/activate")
            ->assertOk()
            ->assertJsonPath('data.active_organization_id', $memberOrganizationId)
            ->assertJsonPath('data.memberships.0.role', 'manager');

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$otherOrganizationId}/activate")
            ->assertNotFound();
    }

    public function test_anonymous_visitor_cannot_manage_connected_workspaces(): void
    {
        $this->getJson('/api/v1/organizations')->assertUnauthorized();
        $this->postJson('/api/v1/organizations', [
            'name' => 'Unauthorized Team',
            'timezone' => 'UTC',
        ])->assertUnauthorized();
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

    private function membership(
        int $organizationId,
        int $userId,
        string $role,
    ): void {
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
