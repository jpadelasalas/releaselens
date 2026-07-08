<?php

namespace App\Modules\Webhooks\Contracts;

use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Enums\WebhookProcessingAttemptStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WebhookDeliveryRepositoryInterface
{
    public function findByGitHubDeliveryId(string $githubDeliveryId): ?object;

    public function findById(int $id): ?object;

    public function findForOrganization(int $organizationId, int $id): ?object;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, object>
     */
    public function attemptsForDelivery(int $deliveryId): Collection;

    /**
     * @return array<string, mixed>
     */
    public function healthSummaryForOrganization(int $organizationId): array;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function repositoryDeliveryHealth(int $organizationId, int $silentThresholdHours = 24): Collection;

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
