<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Contracts\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Support\NotificationRuleCatalog;
use InvalidArgumentException;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly NotificationPreferenceRepositoryInterface $preferences,
    ) {}

    /**
     * @param  array<int, int>  $userIds
     */
    public function notifyUsers(
        int $organizationId,
        array $userIds,
        string $type,
        string $title,
        ?string $body = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): void {
        if (! NotificationRuleCatalog::isKnown($type)) {
            throw new InvalidArgumentException("Unknown notification type [{$type}].");
        }

        $dedupKey = $subjectType !== null && $subjectId !== null
            ? hash('sha256', "{$type}|{$subjectType}|{$subjectId}")
            : null;
        $windowMinutes = NotificationRuleCatalog::dedupWindowMinutes($type);

        foreach (array_unique($userIds) as $userId) {
            if (! $this->preferences->isEnabled($organizationId, $userId, $type)) {
                continue;
            }

            if (
                $dedupKey !== null &&
                $windowMinutes > 0 &&
                $this->notifications->existsWithinWindow($userId, $dedupKey, $windowMinutes)
            ) {
                continue;
            }

            $this->notifications->create(
                $organizationId,
                $userId,
                $type,
                $title,
                $body,
                $subjectType,
                $subjectId,
                $dedupKey,
            );
        }
    }
}
