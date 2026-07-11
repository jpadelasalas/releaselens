<?php

namespace App\Modules\Deployments\Handlers;

use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles the deployment_status webhook. A deployment_status event for a
 * deployment we haven't recorded yet is a benign no-op, not a failure.
 */
class DeploymentStatusWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly DeploymentRepositoryInterface $deployments,
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $statusPayload = $payload['deployment_status'] ?? null;
        $deploymentPayload = $payload['deployment'] ?? null;

        if (! is_array($statusPayload) || ! is_array($deploymentPayload)) {
            throw new WebhookProcessingException(
                'The deployment_status webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        $deployment = $this->deployments->findByGitHubDeploymentId((int) $deploymentPayload['id']);

        if ($deployment === null) {
            return;
        }

        $rawStatus = (string) ($statusPayload['state'] ?? 'pending');

        $this->deployments->recordStatusEvent((int) $deployment->id, [
            'status' => $this->normalizeStatus($rawStatus),
            'original_status' => $rawStatus,
            'description' => $statusPayload['description'] ?? null,
            'log_url' => $statusPayload['log_url'] ?? null,
            'environment_url' => $statusPayload['environment_url'] ?? null,
            'occurred_at' => $statusPayload['created_at'] ?? now(),
        ]);
    }

    private function normalizeStatus(string $rawStatus): string
    {
        $known = array_map(fn (DeploymentStatus $status): string => $status->value, DeploymentStatus::cases());

        return in_array($rawStatus, $known, true) ? $rawStatus : DeploymentStatus::Pending->value;
    }
}
