<?php

namespace App\Modules\Deployments\Handlers;

use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Deployments\Contracts\EnvironmentMappingRepositoryInterface;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Handles the deployment webhook (created). A repository that isn't
 * monitored locally is a benign no-op, not a failure.
 */
class DeploymentWebhookHandler implements WebhookEventHandlerInterface
{
    public function __construct(
        private readonly SynchronizationRepositoryInterface $synchronization,
        private readonly DeploymentRepositoryInterface $deployments,
        private readonly EnvironmentMappingRepositoryInterface $environments,
    ) {}

    public function handle(object $delivery, array $payload): void
    {
        $deploymentPayload = $payload['deployment'] ?? null;
        $githubRepositoryId = $payload['repository']['id'] ?? null;

        if (! is_array($deploymentPayload) || ! is_numeric($githubRepositoryId)) {
            throw new WebhookProcessingException(
                'The deployment webhook payload is missing required fields.',
                category: 'validation',
                retryable: false,
            );
        }

        $repository = $this->synchronization->repositoryByGitHubId((int) $githubRepositoryId);

        if ($repository === null) {
            return;
        }

        $rawEnvironment = (string) ($deploymentPayload['environment'] ?? 'unknown');
        $resolved = $this->environments->resolve((int) $repository->organization_id, $rawEnvironment);

        $this->deployments->upsertFromWebhook((int) $repository->organization_id, (int) $repository->id, [
            'github_deployment_id' => (int) $deploymentPayload['id'],
            'ref' => (string) ($deploymentPayload['ref'] ?? ''),
            'sha' => (string) ($deploymentPayload['sha'] ?? ''),
            'original_environment' => $rawEnvironment,
            'normalized_environment' => $resolved['normalized_environment'],
            'is_production' => $resolved['is_production'],
            'description' => $deploymentPayload['description'] ?? null,
            'created_at_github' => $deploymentPayload['created_at'] ?? now(),
        ]);
    }
}
