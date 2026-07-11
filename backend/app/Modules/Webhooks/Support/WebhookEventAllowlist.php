<?php

namespace App\Modules\Webhooks\Support;

/**
 * Supported GitHub event/action combinations for the V2.0 increment
 * (docs/v2 blueprint section 13.2). Anything not listed here is
 * recorded as "ignored" rather than failed (BR2-007, V2-FR-WH-010).
 */
class WebhookEventAllowlist
{
    /**
     * @var array<string, array<int, string>|null>
     */
    private const SUPPORTED = [
        'ping' => null,
        'pull_request' => [
            'opened',
            'edited',
            'synchronize',
            'reopened',
            'closed',
            'converted_to_draft',
            'ready_for_review',
            'review_requested',
            'review_request_removed',
        ],
        'pull_request_review' => ['submitted', 'edited', 'dismissed'],
        'installation' => ['created', 'deleted', 'suspend', 'unsuspend', 'new_permissions_accepted'],
        'installation_repositories' => ['added', 'removed'],
        'repository' => ['renamed', 'archived', 'unarchived', 'transferred', 'deleted'],
        'deployment' => null,
        'deployment_status' => null,
    ];

    public function supports(string $eventName, ?string $actionName): bool
    {
        if (! array_key_exists($eventName, self::SUPPORTED)) {
            return false;
        }

        $actions = self::SUPPORTED[$eventName];

        if ($actions === null) {
            return true;
        }

        return $actionName !== null && in_array($actionName, $actions, true);
    }
}
