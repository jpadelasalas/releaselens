<?php

namespace App\Modules\Releases\Contracts;

use Illuminate\Support\Collection;

interface ReleaseChecklistRepositoryInterface
{
    public function add(int $releaseId, string $label, bool $isRequired): object;

    public function forRelease(int $releaseId): Collection;

    public function find(int $releaseId, int $itemId): ?object;

    public function complete(int $itemId, int $completedByUserId): object;

    public function uncomplete(int $itemId): object;

    public function remove(int $releaseId, int $itemId): void;

    public function hasIncompleteRequiredItems(int $releaseId): bool;
}
