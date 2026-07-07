<?php

namespace App\Modules\Webhooks\Repositories;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    public function findForOrganization(int $organizationId, int $id): ?object
    {
        return DB::table('webhook_deliveries')
            ->where('organization_id', $organizationId)
            ->where('id', $id)
            ->first();
    }

    public function paginateForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('webhook_deliveries')->where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['event_name'])) {
            $query->where('event_name', $filters['event_name']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function attemptsForDelivery(int $deliveryId): Collection
    {
        return DB::table('webhook_processing_attempts')
            ->where('webhook_delivery_id', $deliveryId)
            ->orderBy('attempt_number')
            ->get();
    }

    public function create(array $attributes): object
    {
        $now = now();
        $payload = $attributes['payload'] ?? null;
        unset($attributes['payload']);

        $id = (int) DB::table('webhook_deliveries')->insertGetId([
            'status' => WebhookDeliveryStatus::Received->value,
            'received_at' => $now,
            ...$attributes,
            'payload' => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
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
