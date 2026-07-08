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

    public function healthSummaryForOrganization(int $organizationId): array
    {
        $recent = DB::table('webhook_deliveries')
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['status', 'received_at', 'processed_at']);
        $sampleSize = $recent->count();
        $failedInSample = $recent->whereIn('status', ['retryable_failed', 'dead_lettered'])->count();
        $lags = $recent
            ->filter(fn (object $delivery): bool => $delivery->processed_at !== null && $delivery->received_at !== null)
            ->map(fn (object $delivery): int => CarbonImmutable::parse($delivery->processed_at)
                ->diffInSeconds(CarbonImmutable::parse($delivery->received_at)));

        return [
            'last_delivery_received_at' => DB::table('webhook_deliveries')
                ->where('organization_id', $organizationId)
                ->max('received_at'),
            'dead_letter_count' => DB::table('webhook_deliveries')
                ->where('organization_id', $organizationId)
                ->where('status', 'dead_lettered')
                ->count(),
            'failure_rate' => $sampleSize > 0 ? round($failedInSample / $sampleSize, 4) : null,
            'failure_rate_sample_size' => $sampleSize,
            'average_processing_lag_seconds' => $lags->isNotEmpty() ? (int) round($lags->avg()) : null,
        ];
    }

    public function repositoryDeliveryHealth(int $organizationId, int $silentThresholdHours = 24): Collection
    {
        $threshold = CarbonImmutable::now()->subHours($silentThresholdHours);

        return DB::table('repositories')
            ->where('organization_id', $organizationId)
            ->get(['id', 'full_name'])
            ->map(function (object $repository) use ($threshold): array {
                $lastDeliveryReceivedAt = DB::table('webhook_deliveries')
                    ->where('repository_id', $repository->id)
                    ->max('received_at');
                $deadLetterCount = DB::table('webhook_deliveries')
                    ->where('repository_id', $repository->id)
                    ->where('status', 'dead_lettered')
                    ->count();

                return [
                    'repository_id' => (int) $repository->id,
                    'full_name' => $repository->full_name,
                    'last_delivery_received_at' => $lastDeliveryReceivedAt,
                    'dead_letter_count' => $deadLetterCount,
                    'status' => match (true) {
                        $lastDeliveryReceivedAt === null => 'unknown',
                        CarbonImmutable::parse($lastDeliveryReceivedAt)->lessThan($threshold) => 'unknown',
                        $deadLetterCount > 0 => 'degraded',
                        default => 'healthy',
                    },
                ];
            });
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
