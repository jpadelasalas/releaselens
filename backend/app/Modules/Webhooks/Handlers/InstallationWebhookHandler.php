<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles installation webhook deliveries (created, deleted, suspend,
 * unsuspend, new_permissions_accepted). An installation not yet
 * connected to a ReleaseLens organization is a benign no-op.
 */
class InstallationWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly GitHubConnectionRepositoryInterface $connections
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $installationId = $payload['installation']['id'] ?? null;

        if (! is_numeric($installationId)) {
            throw new WebhookProcessingException(
                'The installation webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        match ($delivery->action_name) {
            'suspend' => $this->connections->markSuspendedByInstallationId((int) $installationId),
            'unsuspend' => $this->connections->markUnsuspendedByInstallationId((int) $installationId),
            'deleted' => $this->connections->markDisconnectedByInstallationId((int) $installationId),
            default => null,
        };
    }
}
