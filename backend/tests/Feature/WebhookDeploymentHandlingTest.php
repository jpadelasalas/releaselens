<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookDeploymentHandlingTest extends TestCase
{
    use RefreshDatabase;

    private int $githubRepositoryId = 77_000_001;

    public function test_deployment_created_stores_a_local_record_with_default_normalization(): void
    {
        $this->repository();

        $this->processDelivery('deployment', null, [
            'deployment' => [
                'id' => 500001,
                'ref' => 'main',
                'sha' => 'abc123',
                'environment' => 'Production',
                'description' => 'Deploy release',
                'created_at' => '2026-07-01T00:00:00Z',
            ],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $deployment = DB::table('deployments')->where('github_deployment_id', 500001)->first();
        $this->assertNotNull($deployment);
        $this->assertSame('Production', $deployment->original_environment);
        $this->assertSame('production', $deployment->normalized_environment);
        $this->assertSame(0, (int) $deployment->is_production);
        $this->assertSame('pending', $deployment->status);
    }

    public function test_deployment_uses_the_configured_environment_mapping(): void
    {
        $organizationId = $this->repositoryWithMapping('prod-us', 'production', true);

        $this->processDelivery('deployment', null, [
            'deployment' => [
                'id' => 500002,
                'ref' => 'main',
                'sha' => 'abc456',
                'environment' => 'prod-us',
                'created_at' => '2026-07-01T00:00:00Z',
            ],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $deployment = DB::table('deployments')->where('github_deployment_id', 500002)->first();
        $this->assertSame('production', $deployment->normalized_environment);
        $this->assertSame(1, (int) $deployment->is_production);
        $this->assertSame($organizationId, $deployment->organization_id);
    }

    public function test_deployment_status_appends_an_event_and_updates_the_deployment(): void
    {
        $this->repository();
        $this->processDelivery('deployment', null, [
            'deployment' => ['id' => 500003, 'ref' => 'main', 'sha' => 'abc789', 'environment' => 'staging'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->processDelivery('deployment_status', null, [
            'deployment' => ['id' => 500003],
            'deployment_status' => [
                'state' => 'success',
                'description' => 'Deploy finished',
                'log_url' => 'https://ci.example.com/1',
                'created_at' => '2026-07-01T00:05:00Z',
            ],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $deployment = DB::table('deployments')->where('github_deployment_id', 500003)->first();
        $this->assertSame('success', $deployment->status);

        $event = DB::table('deployment_status_events')->where('deployment_id', $deployment->id)->first();
        $this->assertNotNull($event);
        $this->assertSame('success', $event->status);
        $this->assertSame('Deploy finished', $event->description);
    }

    public function test_deployment_status_for_an_unknown_deployment_is_a_benign_no_op(): void
    {
        $this->repository();

        $this->processDelivery('deployment_status', null, [
            'deployment' => ['id' => 999_999],
            'deployment_status' => ['state' => 'success'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->assertSame(0, DB::table('deployment_status_events')->count());
        $this->assertSame('processed', DB::table('webhook_deliveries')->latest('id')->value('status'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processDelivery(string $eventName, ?string $actionName, array $payload): void
    {
        $deliveries = app(WebhookDeliveryRepositoryInterface::class);
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-'.uniqid('', true),
            'event_name' => $eventName,
            'action_name' => $actionName,
            'payload_sha256' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        (new ProcessWebhookDeliveryJob($delivery->id, $payload))->handle(
            $deliveries,
            app(WebhookEventAllowlist::class),
            app(WebhookEventHandlerRegistry::class),
        );
    }

    private function repository(): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => $this->githubRepositoryId,
            'name' => 'widgets',
            'full_name' => 'acme/widgets',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repositoryWithMapping(string $sourceEnvironment, string $normalized, bool $isProduction): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => $this->githubRepositoryId,
            'name' => 'widgets',
            'full_name' => 'acme/widgets',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('environment_mappings')->insert([
            'organization_id' => $organizationId,
            'source_environment' => $sourceEnvironment,
            'normalized_environment' => $normalized,
            'is_production' => $isProduction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $organizationId;
    }
}
