<?php

namespace App\Modules\GitHub\Contracts;

interface GitHubConnectionRepositoryInterface
{
    public function activeForOrganization(int $organizationId): ?object;

    public function latestForOrganization(int $organizationId): ?object;

    public function activeByInstallationId(int $installationId): ?object;

    /** @param array<string, mixed> $metadata */
    public function connect(int $organizationId, int $installationId, array $metadata): object;

    /** @param array<string, mixed> $metadata */
    public function refreshMetadata(int $installationRecordId, array $metadata): object;

    public function markDisconnectedRemotely(
        int $organizationId,
        int $installationRecordId,
        int $actorUserId,
        ?string $ipAddress,
        ?string $userAgent,
    ): object;

    public function disconnect(
        int $organizationId,
        int $installationRecordId,
        int $actorUserId,
        ?string $ipAddress,
        ?string $userAgent,
    ): void;

    /** @param array<string, mixed> $metadata */
    public function recordAuditEvent(
        int $organizationId,
        int $actorUserId,
        string $eventType,
        int $installationRecordId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void;
}
