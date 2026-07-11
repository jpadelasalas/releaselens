<?php

namespace App\Modules\Notifications\Contracts;

use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function create(
        int $organizationId,
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?string $subjectType,
        ?int $subjectId,
        ?string $dedupKey,
    ): object;

    public function existsWithinWindow(int $userId, string $dedupKey, int $windowMinutes): bool;

    public function forUser(int $organizationId, int $userId, bool $unreadOnly = false): Collection;

    public function unreadCount(int $organizationId, int $userId): int;

    public function markRead(int $organizationId, int $userId, int $id): void;

    public function markAllRead(int $organizationId, int $userId): void;
}
