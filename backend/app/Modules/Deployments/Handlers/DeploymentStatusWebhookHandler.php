<?php

namespace App\Modules\Deployments\Handlers;

use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
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
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
        private readonly NotificationService $notifications,
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
        $normalizedStatus = $this->normalizeStatus($rawStatus);
        $previousStatus = $deployment->status;

        $this->deployments->recordStatusEvent((int) $deployment->id, [
            'status' => $normalizedStatus,
            'original_status' => $rawStatus,
            'description' => $statusPayload['description'] ?? null,
            'log_url' => $statusPayload['log_url'] ?? null,
            'environment_url' => $statusPayload['environment_url'] ?? null,
            'occurred_at' => $statusPayload['created_at'] ?? now(),
        ]);

        $this->notifyOnStatusChange($deployment, $previousStatus, $normalizedStatus);
    }

    private function notifyOnStatusChange(object $deployment, string $previousStatus, string $normalizedStatus): void
    {
        $type = match (true) {
            $normalizedStatus === DeploymentStatus::Failure->value => NotificationType::DeploymentFailed,
            $normalizedStatus === DeploymentStatus::Inactive->value && $previousStatus === DeploymentStatus::Success->value => NotificationType::DeploymentRolledBack,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $userIds = $this->organizations
            ->membersForOrganization((int) $deployment->organization_id)
            ->filter(fn (object $member): bool => in_array($member->role, ['owner', 'manager'], true))
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->notifications->notifyUsers(
            organizationId: (int) $deployment->organization_id,
            userIds: $userIds,
            type: $type->value,
            title: $type === NotificationType::DeploymentFailed
                ? 'A deployment failed'
                : 'A deployment was rolled back',
            subjectType: 'deployment',
            subjectId: (int) $deployment->id,
        );
    }

    private function normalizeStatus(string $rawStatus): string
    {
        $known = array_map(fn (DeploymentStatus $status): string => $status->value, DeploymentStatus::cases());

        return in_array($rawStatus, $known, true) ? $rawStatus : DeploymentStatus::Pending->value;
    }
}
