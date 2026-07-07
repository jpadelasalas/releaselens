<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookDeliveryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_defaults_to_received_status(): void
    {
        $repository = $this->repository();

        $delivery = $repository->create([
            'github_delivery_id' => 'delivery-1',
            'event_name' => 'ping',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->assertSame('received', $delivery->status);
        $this->assertNotNull($delivery->received_at);
    }

    public function test_create_does_not_require_an_organization_or_repository(): void
    {
        $repository = $this->repository();

        $delivery = $repository->create([
            'github_delivery_id' => 'delivery-unmapped',
            'event_name' => 'ping',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->assertNull($delivery->organization_id);
        $this->assertNull($delivery->repository_id);
    }

    public function test_duplicate_github_delivery_id_is_rejected_by_the_schema(): void
    {
        $repository = $this->repository();
        $repository->create([
            'github_delivery_id' => 'delivery-duplicate',
            'event_name' => 'ping',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->expectException(QueryException::class);

        $repository->create([
            'github_delivery_id' => 'delivery-duplicate',
            'event_name' => 'ping',
            'payload_sha256' => hash('sha256', '{}'),
        ]);
    }

    public function test_find_by_github_delivery_id_supports_duplicate_detection(): void
    {
        $repository = $this->repository();
        $repository->create([
            'github_delivery_id' => 'delivery-2',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->assertNotNull($repository->findByGitHubDeliveryId('delivery-2'));
        $this->assertNull($repository->findByGitHubDeliveryId('does-not-exist'));
    }

    public function test_update_status_persists_the_new_status_and_extra_fields(): void
    {
        $repository = $this->repository();
        $delivery = $repository->create([
            'github_delivery_id' => 'delivery-3',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $repository->updateStatus($delivery->id, WebhookDeliveryStatus::Ignored, [
            'error_category' => 'unsupported_action',
        ]);

        $updated = $repository->findByGitHubDeliveryId('delivery-3');

        $this->assertSame('ignored', $updated->status);
        $this->assertSame('unsupported_action', $updated->error_category);
    }

    public function test_record_attempt_increments_the_attempt_number_per_delivery(): void
    {
        $repository = $this->repository();
        $delivery = $repository->create([
            'github_delivery_id' => 'delivery-4',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $first = $repository->recordAttempt($delivery->id, WebhookProcessingAttemptStatus::Failed, 'transient', 'timeout');
        $second = $repository->recordAttempt($delivery->id, WebhookProcessingAttemptStatus::Succeeded);

        $this->assertSame(1, $first->attempt_number);
        $this->assertSame(2, $second->attempt_number);
        $this->assertSame('failed', $first->status);
        $this->assertSame('succeeded', $second->status);
    }

    public function test_attempt_number_is_unique_per_delivery_at_the_schema_level(): void
    {
        $repository = $this->repository();
        $delivery = $repository->create([
            'github_delivery_id' => 'delivery-5',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->expectException(QueryException::class);

        DB::table('webhook_processing_attempts')->insert([
            'webhook_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('webhook_processing_attempts')->insert([
            'webhook_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(): WebhookDeliveryRepositoryInterface
    {
        return app(WebhookDeliveryRepositoryInterface::class);
    }
}
