<?php

namespace App\Modules\Webhooks\Repositories;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WebhookDeliveryRepository implements WebhookDeliveryRepositoryInterface
{
    public function findByGitHubDeliveryId(string $githubDeliveryId): ?object
    {
        return DB::table('webhook_deliveries')
            ->where('github_delivery_id', $githubDeliveryId)
            ->first();
    }

    public function findById(int $id): ?object
    {
        return DB::table('webhook_deliveries')->find($id);
    }

    public function create(array $attributes): object
    {
        $now = now();
        $id = (int) DB::table('webhook_deliveries')->insertGetId([
            'status' => WebhookDeliveryStatus::Received->value,
            'received_at' => $now,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('webhook_deliveries')->find($id);
    }

    public function updateStatus(int $id, WebhookDeliveryStatus $status, array $extra = []): void
    {
        DB::table('webhook_deliveries')->where('id', $id)->update([
            ...$extra,
            'status' => $status->value,
            'updated_at' => now(),
        ]);
    }

    public function recordAttempt(
        int $webhookDeliveryId,
        WebhookProcessingAttemptStatus $status,
        ?string $errorCategory = null,
        ?string $errorSummary = null,
        ?CarbonImmutable $nextRetryAt = null,
    ): object {
        $attemptNumber = 1 + (int) DB::table('webhook_processing_attempts')
            ->where('webhook_delivery_id', $webhookDeliveryId)
            ->max('attempt_number');
        $now = now();
        $id = (int) DB::table('webhook_processing_attempts')->insertGetId([
            'webhook_delivery_id' => $webhookDeliveryId,
            'attempt_number' => $attemptNumber,
            'status' => $status->value,
            'started_at' => $now,
            'completed_at' => $status === WebhookProcessingAttemptStatus::Processing ? null : $now,
            'next_retry_at' => $nextRetryAt,
            'error_category' => $errorCategory,
            'error_summary' => $errorSummary,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('webhook_processing_attempts')->find($id);
    }
}
