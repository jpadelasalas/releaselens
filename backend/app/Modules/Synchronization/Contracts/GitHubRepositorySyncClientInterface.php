<?php

namespace App\Modules\Synchronization\Contracts;

interface GitHubRepositorySyncClientInterface
{
    /** @return array<string, mixed> */
    public function synchronize(
        int $installationId,
        string $repositoryFullName,
        ?string $cursor,
    ): array;
}
