<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles installation_repositories webhook deliveries (added,
 * removed). A repository named in the payload that isn't monitored
 * locally is a benign no-op.
 */
class InstallationRepositoriesWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $added = $payload['repositories_added'] ?? [];
        $removed = $payload['repositories_removed'] ?? [];

        if (! is_array($added) || ! is_array($removed)) {
            throw new WebhookProcessingException(
                'The installation_repositories webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        foreach ($removed as $repositoryPayload) {
            $this->markAccessibility($repositoryPayload, isAccessible: false, accessError: 'installation_access_removed');
        }

        foreach ($added as $repositoryPayload) {
            $this->markAccessibility($repositoryPayload, isAccessible: true, accessError: null);
        }
    }

    private function markAccessibility(mixed $repositoryPayload, bool $isAccessible, ?string $accessError): void
    {
        if (! is_array($repositoryPayload) || ! is_numeric($repositoryPayload['id'] ?? null)) {
            return;
        }

        $repository = $this->synchronization->repositoryByGitHubId((int) $repositoryPayload['id']);

        if ($repository === null) {
            return;
        }

        $this->synchronization->markRepositoryAccessibility((int) $repository->id, $isAccessible, $accessError);
    }
}
