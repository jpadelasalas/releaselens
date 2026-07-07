<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles pull_request webhook deliveries (opened, edited, synchronize,
 * reopened, closed, converted_to_draft, ready_for_review). A repository
 * that isn't monitored locally is a benign no-op, not a failure.
 */
class PullRequestWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $pullRequestPayload = $payload['pull_request'] ?? null;
        $githubRepositoryId = $payload['repository']['id'] ?? null;

        if (! is_array($pullRequestPayload) || ! is_numeric($githubRepositoryId)) {
            throw new WebhookProcessingException(
                'The pull_request webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        $repository = $this->synchronization->repositoryByGitHubId((int) $githubRepositoryId);

        if ($repository === null) {
            return;
        }

        $this->synchronization->upsertPullRequestFromWebhook((int) $repository->id, $pullRequestPayload);
    }
}
