<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.notifications', true);
    }

    public function test_a_member_can_list_their_own_notifications(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        app(NotificationRepositoryInterface::class)->create(
            $organizationId,
            $viewer->id,
            'release.released',
            'Released',
            null,
            'release',
            1,
            null,
        );

        $response = $this->actingAs($viewer)->getJson(
            "/api/v1/organizations/{$organizationId}/notifications",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('meta.unread_count', 1);
    }

    public function test_a_non_member_cannot_list_another_organizations_notifications(): void
    {
        $organizationId = $this->organization();
        $outsider = $this->member($this->organization(), 'viewer');

        $response = $this->actingAs($outsider)->getJson(
            "/api/v1/organizations/{$organizationId}/notifications",
        );

        $response->assertForbidden();
    }

    public function test_marking_a_notification_read_clears_the_unread_count(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        $notification = app(NotificationRepositoryInterface::class)->create(
            $organizationId,
            $viewer->id,
            'release.released',
            'Released',
            null,
            null,
            null,
            null,
        );

        $response = $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/notifications/{$notification->id}/read",
        );

        $response->assertOk();
        $this->assertSame(
            0,
            app(NotificationRepositoryInterface::class)->unreadCount($organizationId, $viewer->id),
        );
    }

    public function test_a_member_cannot_mark_another_members_notification_read(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        $owner = $this->member($organizationId, 'owner');
        $notification = app(NotificationRepositoryInterface::class)->create(
            $organizationId,
            $owner->id,
            'release.released',
            'Released',
            null,
            null,
            null,
            null,
        );

        $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/notifications/{$notification->id}/read",
        )->assertOk();

        $this->assertSame(
            1,
            app(NotificationRepositoryInterface::class)->unreadCount($organizationId, $owner->id),
        );
    }

    public function test_mark_all_read_clears_every_notification_for_the_member(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        $repository = app(NotificationRepositoryInterface::class);
        $repository->create($organizationId, $viewer->id, 'release.released', 'A', null, null, null, null);
        $repository->create($organizationId, $viewer->id, 'release.released', 'B', null, null, null, null);

        $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/notifications/read-all",
        )->assertOk();

        $this->assertSame(0, $repository->unreadCount($organizationId, $viewer->id));
    }

    public function test_a_member_can_list_and_update_their_notification_preferences(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');

        $index = $this->actingAs($viewer)->getJson(
            "/api/v1/organizations/{$organizationId}/notification-preferences",
        );
        $index->assertOk();
        $this->assertTrue(collect($index->json('data'))->every(fn (array $p): bool => $p['enabled'] === true));

        $update = $this->actingAs($viewer)->putJson(
            "/api/v1/organizations/{$organizationId}/notification-preferences",
            ['type' => 'release.released', 'enabled' => false],
        );
        $update->assertOk();
        $update->assertJsonPath('data.enabled', false);
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
