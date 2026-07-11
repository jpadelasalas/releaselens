<?php

namespace App\Modules\Ai\Contracts;

use Illuminate\Support\Collection;

interface AiGenerationRepositoryInterface
{
    /**
     * @param  array<int, string>  $inputFields
     */
    public function record(
        int $organizationId,
        int $releaseId,
        ?int $requestedByUserId,
        string $provider,
        string $status,
        array $inputFields,
        ?string $output,
        ?string $errorMessage,
    ): object;

    public function countForOrganizationSince(int $organizationId, \DateTimeInterface $since): int;

    public function forRelease(int $releaseId): Collection;
}
