<?php

namespace App\Modules\Webhooks\Contracts;

use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use Carbon\CarbonImmutable;

interface WebhookDeliveryRepositoryInterface
{
    public function findByGitHubDeliveryId(string $githubDeliveryId): ?object;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): object;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function updateStatus(int $id, WebhookDeliveryStatus $status, array $extra = []): void;

    public function recordAttempt(
        int $webhookDeliveryId,
        WebhookProcessingAttemptStatus $status,
        ?string $errorCategory = null,
        ?string $errorSummary = null,
        ?CarbonImmutable $nextRetryAt = null,
    ): object;
}
