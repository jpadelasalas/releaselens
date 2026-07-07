<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles repository webhook deliveries (renamed, archived,
 * unarchived, transferred, deleted). A repository not monitored
 * locally is a benign no-op.
 */
class RepositoryWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $repositoryPayload = $payload['repository'] ?? null;

        if (! is_array($repositoryPayload) || ! is_numeric($repositoryPayload['id'] ?? null)) {
            throw new WebhookProcessingException(
                'The repository webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        $repository = $this->synchronization->repositoryByGitHubId((int) $repositoryPayload['id']);

        if ($repository === null) {
            return;
        }

        match ($delivery->action_name) {
            'renamed', 'transferred' => $this->synchronization->updateRepositoryMetadataFromWebhook(
                (int) $repository->id,
                $repositoryPayload,
            ),
            'archived' => $this->synchronization->updateRepositoryMetadataFromWebhook(
                (int) $repository->id,
                ['archived' => true],
            ),
            'unarchived' => $this->synchronization->updateRepositoryMetadataFromWebhook(
                (int) $repository->id,
                ['archived' => false],
            ),
            'deleted' => $this->synchronization->markRepositoryAccessibility(
                (int) $repository->id,
                false,
                'repository_deleted',
            ),
            default => null,
        };
    }
}
