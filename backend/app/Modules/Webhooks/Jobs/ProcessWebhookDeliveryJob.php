<?php

namespace App\Modules\Webhooks\Jobs;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Routes one webhook delivery to its handler and classifies failures:
 * unsupported event/action -> ignored, transient handler failure ->
 * retryable_failed with bounded backoff, permanent handler failure or
 * a missing handler -> dead_lettered immediately (docs/v2 blueprint
 * section 13.3/13.8).
 */
class ProcessWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $webhookDeliveryId,
        public readonly array $payload = [],
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map(
            fn (int $seconds): int => $seconds + random_int(0, 30),
            [30, 120, 300, 600],
        );
    }

    public function handle(
        WebhookDeliveryRepositoryInterface $deliveries,
        WebhookEventAllowlist $allowlist,
        WebhookEventHandlerRegistry $handlers,
    ): void {
        $delivery = $deliveries->findById($this->webhookDeliveryId);

        if ($delivery === null) {
            return;
        }

        $deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::Processing);

        if (! $allowlist->supports($delivery->event_name, $delivery->action_name)) {
            $deliveries->recordAttempt($delivery->id, WebhookProcessingAttemptStatus::Succeeded);
            $deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::Ignored, [
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $handlers->handle($delivery->event_name, $delivery, $this->payload);

            $deliveries->recordAttempt($delivery->id, WebhookProcessingAttemptStatus::Succeeded);
            $deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::Processed, [
                'processed_at' => now(),
            ]);
        } catch (WebhookProcessingException $exception) {
            $deliveries->recordAttempt(
                $delivery->id,
                WebhookProcessingAttemptStatus::Failed,
                $exception->category,
                $exception->getMessage(),
                $exception->retryable ? $this->nextRetryAt() : null,
            );

            if ($exception->retryable) {
                $deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::RetryableFailed, [
                    'error_category' => $exception->category,
                    'error_summary' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            $deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::DeadLettered, [
                'error_category' => $exception->category,
                'error_summary' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(WebhookDeliveryRepositoryInterface::class)->updateStatus(
            $this->webhookDeliveryId,
            WebhookDeliveryStatus::DeadLettered,
        );
    }

    private function nextRetryAt(): CarbonImmutable
    {
        $attemptIndex = max(0, $this->attempts() - 1);
        $backoff = $this->backoff();

        return CarbonImmutable::now()->addSeconds($backoff[$attemptIndex] ?? end($backoff));
    }
}
