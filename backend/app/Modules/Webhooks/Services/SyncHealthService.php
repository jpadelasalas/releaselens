<?php

namespace App\Modules\Webhooks\Services;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;

class SyncHealthService
{
    public function __construct(
        private readonly WebhookDeliveryRepositoryInterface $deliveries,
        private readonly SynchronizationRepositoryInterface $synchronization,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(int $organizationId): array
    {
        return [
            ...$this->deliveries->healthSummaryForOrganization($organizationId),
            ...$this->synchronization->reconciliationHealthForOrganization($organizationId),
            'repositories' => $this->deliveries->repositoryDeliveryHealth($organizationId)->all(),
        ];
    }
}
