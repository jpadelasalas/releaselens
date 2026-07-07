<?php

namespace App\Modules\Webhooks\Jobs;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder handler dispatched by the ingress endpoint (T1.2).
 *
 * T1.3 replaces this body with the event/action router, allowlist,
 * and retry classification. For now it only proves the ingress ->
 * queue -> ledger path is wired end to end.
 */
class ProcessWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $webhookDeliveryId) {}

    public function handle(WebhookDeliveryRepositoryInterface $deliveries): void
    {
        $deliveries->updateStatus($this->webhookDeliveryId, WebhookDeliveryStatus::Processed, [
            'processed_at' => now(),
        ]);
    }
}
