<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles pull_request_review webhook deliveries (submitted, edited,
 * dismissed). Upserts the parent pull request first so a review can
 * never reference a pull request row that doesn't exist yet.
 */
class PullRequestReviewWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $pullRequestPayload = $payload['pull_request'] ?? null;
        $reviewPayload = $payload['review'] ?? null;
        $githubRepositoryId = $payload['repository']['id'] ?? null;

        if (! is_array($pullRequestPayload) || ! is_array($reviewPayload) || ! is_numeric($githubRepositoryId)) {
            throw new WebhookProcessingException(
                'The pull_request_review webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        $repository = $this->synchronization->repositoryByGitHubId((int) $githubRepositoryId);

        if ($repository === null) {
            return;
        }

        $pullRequest = $this->synchronization->upsertPullRequestFromWebhook(
            (int) $repository->id,
            $pullRequestPayload,
        );

        if ($pullRequest === null) {
            return;
        }

        $this->synchronization->upsertReviewFromWebhook((int) $pullRequest->id, $reviewPayload);
    }
}
