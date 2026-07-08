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

    public function test_sync_health_reports_dead_letter_count_and_failure_rate(): void
    {
        $organizationId = $this->organization();
        $this->delivery($organizationId, 'delivery-ok-1', 'processed');
        $this->delivery($organizationId, 'delivery-ok-2', 'processed');
        $this->delivery($organizationId, 'delivery-dead', 'dead_lettered');
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/sync-health");

        $response->assertOk()
            ->assertJsonPath('data.dead_letter_count', 1)
            ->assertJsonPath('data.failure_rate_sample_size', 3)
            ->assertJsonPath('data.failure_rate', round(1 / 3, 4));
    }

    public function test_sync_health_marks_a_silent_repository_as_unknown_not_broken(): void
    {
        $organizationId = $this->organization();
        $this->repository($organizationId, 'silent-repo');
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/sync-health");

        $response->assertOk()->assertJsonPath('data.repositories.0.status', 'unknown');
    }

    public function test_sync_health_marks_a_repository_with_dead_letters_as_degraded(): void
    {
        $organizationId = $this->organization();
        $repositoryId = $this->repository($organizationId, 'degraded-repo');
        DB::table('webhook_deliveries')->insert([
            'organization_id' => $organizationId,
            'repository_id' => $repositoryId,
            'github_delivery_id' => 'delivery-degraded',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
            'status' => 'dead_lettered',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/sync-health");

        $response->assertOk()
            ->assertJsonPath('data.repositories.0.status', 'degraded')
            ->assertJsonPath('data.repositories.0.dead_letter_count', 1);
    }

    public function test_sync_health_reports_reconciliation_corrections(): void
    {
        $organizationId = $this->organization();
        $repositoryId = $this->repository($organizationId, 'reconciled-repo');
        DB::table('sync_runs')->insert([
            'repository_id' => $repositoryId,
            'trigger_type' => 'reconciliation',
            'status' => 'success',
            'updated_count' => 4,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = $this->member($organizationId, 'owner');

        $response = $this->actingAs($user)->getJson("/api/v1/organizations/{$organizationId}/sync-health");

        $response->assertOk()->assertJsonPath('data.reconciliation_corrections', 4);
        $this->assertNotNull($response->json('data.last_successful_reconciliation_at'));
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

    private function repository(int $organizationId, string $name): int
    {
        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => random_int(100_000, 999_999),
            'name' => $name,
            'full_name' => "acme/{$name}",
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'never_synced',
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
