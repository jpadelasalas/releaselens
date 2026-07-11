<?php

namespace App\Modules\Notifications\Contracts;

use Illuminate\Support\Collection;

interface NotificationPreferenceRepositoryInterface
{
    public function isEnabled(int $organizationId, int $userId, string $type): bool;

    public function listForUser(int $organizationId, int $userId): Collection;

    public function setEnabled(int $organizationId, int $userId, string $type, bool $enabled): object;
}
