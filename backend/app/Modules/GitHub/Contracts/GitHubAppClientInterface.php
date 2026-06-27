<?php

namespace App\Modules\GitHub\Contracts;

interface GitHubAppClientInterface
{
    /** @return array<string, mixed> */
    public function installation(int $installationId): array;
}
