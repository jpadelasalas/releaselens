<?php

namespace App\Modules\Identity\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $attributes): User;

    public function findByNormalizedEmail(string $normalizedEmail): ?User;

    /** @return Collection<int, object> */
    public function membershipsForUser(int $userId): Collection;
}
