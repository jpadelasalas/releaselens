<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessWebhookDeliveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_event_action_is_marked_ignored_not_failed(): void
    {
        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-unsupported',
            'event_name' => 'pull_request',
            'action_name' => 'labeled',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->runJob($delivery->id);

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('ignored', $updated->status);
        $this->assertNotNull($updated->processed_at);
    }

    public function test_an_allowlisted_event_with_no_registered_handler_is_dead_lettered(): void
    {
        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-no-handler',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->runJob($delivery->id);

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('dead_lettered', $updated->status);
        $this->assertSame('handler_not_implemented', $updated->error_category);
    }

    public function test_a_transient_handler_failure_marks_retryable_failed_and_rethrows(): void
    {
        $this->registerHandler(fn () => throw new WebhookProcessingException('temporary outage', 'transient', true));

        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-transient',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        try {
            $this->runJob($delivery->id);
            $this->fail('Expected the transient failure to be rethrown for the queue to retry.');
        } catch (WebhookProcessingException $exception) {
            $this->assertSame('transient', $exception->category);
        }

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('retryable_failed', $updated->status);
        $this->assertSame('transient', $updated->error_category);

        $attempt = $this->latestAttempt($delivery->id);
        $this->assertSame('failed', $attempt->status);
        $this->assertNotNull($attempt->next_retry_at);
    }

    public function test_a_permanent_handler_failure_is_dead_lettered_without_rethrowing(): void
    {
        $this->registerHandler(fn () => throw new WebhookProcessingException('bad payload shape', 'validation', false));

        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-permanent',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->runJob($delivery->id);

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('dead_lettered', $updated->status);
        $this->assertSame('validation', $updated->error_category);

        $attempt = $this->latestAttempt($delivery->id);
        $this->assertSame('failed', $attempt->status);
        $this->assertNull($attempt->next_retry_at);
    }

    public function test_a_successful_handler_marks_the_delivery_processed(): void
    {
        $this->registerHandler(fn () => null);

        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-success',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        $this->runJob($delivery->id);

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('processed', $updated->status);
        $this->assertNotNull($updated->processed_at);

        $attempt = $this->latestAttempt($delivery->id);
        $this->assertSame('succeeded', $attempt->status);
    }

    public function test_exhausting_retries_dead_letters_the_delivery_via_the_failed_hook(): void
    {
        $deliveries = $this->deliveries();
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-exhausted',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', '{}'),
        ]);

        (new ProcessWebhookDeliveryJob($delivery->id))->failed(null);

        $updated = $deliveries->findById($delivery->id);
        $this->assertSame('dead_lettered', $updated->status);
    }

    private function registerHandler(callable $handle): void
    {
        app(WebhookEventHandlerRegistry::class)->register('pull_request', new class($handle) implements WebhookEventHandlerInterface
        {
            public function __construct(private readonly Closure $handle) {}

            public function handle(object $delivery, array $payload): void
            {
                ($this->handle)();
            }
        });
    }

    private function runJob(int $deliveryId): void
    {
        (new ProcessWebhookDeliveryJob($deliveryId))->handle(
            $this->deliveries(),
            app(WebhookEventAllowlist::class),
            app(WebhookEventHandlerRegistry::class),
        );
    }

    private function deliveries(): WebhookDeliveryRepositoryInterface
    {
        return app(WebhookDeliveryRepositoryInterface::class);
    }

    private function latestAttempt(int $deliveryId): object
    {
        return DB::table('webhook_processing_attempts')
            ->where('webhook_delivery_id', $deliveryId)
            ->latest('attempt_number')
            ->firstOrFail();
    }
}
