<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDeliveryOpsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.webhooks', true);
    }

    public function test_a_member_can_list_only_their_organizations_deliveries(): void
    {
        $organizationId = $this->organization();
        $otherOrganizationId = $this->organization();
        $this->delivery($organizationId, 'delivery-mine', 'processed');
        $this->delivery($otherOrganizationId, 'delivery-not-mine', 'processed');
        $user = $this->member($organizationId, 'viewer');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/webhook-deliveries");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('delivery-mine', $response->json('data.0.github_delivery_id'));
    }

    public function test_a_non_member_cannot_list_another_organizations_deliveries(): void
    {
        $organizationId = $this->organization();
        $this->delivery($organizationId, 'delivery-private', 'processed');
        $outsider = User::query()->create([
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'normalized_email' => 'outsider@example.com',
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($outsider)->getJson("/api/v1/organizations/{$organizationId}/webhook-deliveries");

        $response->assertForbidden();
    }

    public function test_show_includes_processing_attempts(): void
    {
        $organizationId = $this->organization();
        $deliveryId = $this->delivery($organizationId, 'delivery-with-attempts', 'dead_lettered', errorCategory: 'validation');
        DB::table('webhook_processing_attempts')->insert([
            'webhook_delivery_id' => $deliveryId,
            'attempt_number' => 1,
            'status' => 'failed',
            'started_at' => now(),
            'completed_at' => now(),
            'error_category' => 'validation',
            'error_summary' => 'bad payload',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/webhook-deliveries/{$deliveryId}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.attempts')
            ->assertJsonPath('data.attempts.0.error_summary', 'bad payload');
    }

    public function test_owner_can_replay_a_dead_lettered_delivery(): void
    {
        Queue::fake();
        $organizationId = $this->organization();
        $deliveryId = $this->delivery($organizationId, 'delivery-replay', 'dead_lettered', errorCategory: 'transient');
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->postJson(
            "/api/v1/organizations/{$organizationId}/webhook-deliveries/{$deliveryId}/replay"
        );

        $response->assertOk()->assertJsonPath('data.status', 'queued');
        $this->assertSame('queued', DB::table('webhook_deliveries')->where('id', $deliveryId)->value('status'));
        Queue::assertPushed(
            ProcessWebhookDeliveryJob::class,
            fn (ProcessWebhookDeliveryJob $job): bool => $job->webhookDeliveryId === $deliveryId,
        );
    }

    public function test_viewer_cannot_replay(): void
    {
        $organizationId = $this->organization();
        $deliveryId = $this->delivery($organizationId, 'delivery-viewer', 'dead_lettered');
        $user = $this->member($organizationId, 'viewer');

        $response = $this->actingAs($user)->postJson(
            "/api/v1/organizations/{$organizationId}/webhook-deliveries/{$deliveryId}/replay"
        );

        $response->assertForbidden();
    }

    public function test_replay_is_rejected_for_a_delivery_that_is_not_failed(): void
    {
        $organizationId = $this->organization();
        $deliveryId = $this->delivery($organizationId, 'delivery-processed', 'processed');
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->postJson(
            "/api/v1/organizations/{$organizationId}/webhook-deliveries/{$deliveryId}/replay"
        );

        $response->assertStatus(422)->assertJsonPath('error.code', 'WEBHOOK_REPLAY_NOT_ALLOWED');
    }

    public function test_the_ops_endpoints_404_when_the_feature_flag_is_disabled(): void
    {
        config()->set('releaselens.features.webhooks', false);
        $organizationId = $this->organization();
        $user = $this->member($organizationId, 'owner');

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organizationId}/webhook-deliveries")
            ->assertStatus(404);
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

    private function delivery(int $organizationId, string $githubDeliveryId, string $status, ?string $errorCategory = null): int
    {
        return (int) DB::table('webhook_deliveries')->insertGetId([
            'organization_id' => $organizationId,
            'github_delivery_id' => $githubDeliveryId,
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
            'status' => $status,
            'error_category' => $errorCategory,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
