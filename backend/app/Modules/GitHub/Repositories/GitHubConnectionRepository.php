<?php

namespace App\Modules\GitHub\Repositories;

use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class GitHubConnectionRepository implements GitHubConnectionRepositoryInterface
{
    public function activeForOrganization(int $organizationId): ?object
    {
        return DB::table('github_installations')
            ->where('organization_id', $organizationId)
            ->whereNull('disconnected_at')
            ->latest('id')
            ->first();
    }

    public function latestForOrganization(int $organizationId): ?object
    {
        return DB::table('github_installations')
            ->where('organization_id', $organizationId)
            ->latest('id')
            ->first();
    }

    public function activeByInstallationId(int $installationId): ?object
    {
        return DB::table('github_installations')
            ->where('github_installation_id', $installationId)
            ->whereNull('disconnected_at')
            ->first();
    }

    public function markSuspendedByInstallationId(int $installationId): void
    {
        DB::table('github_installations')
            ->where('github_installation_id', $installationId)
            ->update(['suspended_at' => now(), 'updated_at' => now()]);
    }

    public function markUnsuspendedByInstallationId(int $installationId): void
    {
        DB::table('github_installations')
            ->where('github_installation_id', $installationId)
            ->update(['suspended_at' => null, 'updated_at' => now()]);
    }

    public function markDisconnectedByInstallationId(int $installationId): void
    {
        DB::transaction(function () use ($installationId): void {
            $installation = DB::table('github_installations')
                ->where('github_installation_id', $installationId)
                ->first();

            if ($installation === null) {
                return;
            }

            DB::table('github_installations')
                ->where('id', $installation->id)
                ->update(['disconnected_at' => now(), 'updated_at' => now()]);

            DB::table('repositories')
                ->where('github_installation_id', $installation->id)
                ->update(['sync_enabled' => false, 'updated_at' => now()]);
        });
    }

    public function connect(int $organizationId, int $installationId, array $metadata): object
    {
        $now = now();
        $values = [
            'organization_id' => $organizationId,
            'github_account_id' => $metadata['account']['id'] ?? null,
            'github_account_login' => $metadata['account']['login'] ?? null,
            'github_account_type' => $metadata['account']['type'] ?? null,
            'repository_selection' => $metadata['repository_selection'] ?? null,
            'permissions' => json_encode($metadata['permissions'] ?? [], JSON_THROW_ON_ERROR),
            'connected_at' => $now,
            'suspended_at' => $metadata['suspended_at'] ?? null,
            'disconnected_at' => null,
            'updated_at' => $now,
        ];

        DB::table('github_installations')->updateOrInsert(
            ['github_installation_id' => $installationId],
            [...$values, 'created_at' => $now],
        );

        return DB::table('github_installations')
            ->where('github_installation_id', $installationId)
            ->first();
    }

    public function refreshMetadata(int $installationRecordId, array $metadata): object
    {
        DB::table('github_installations')
            ->where('id', $installationRecordId)
            ->update([
                'github_account_id' => $metadata['account']['id'] ?? null,
                'github_account_login' => $metadata['account']['login'] ?? null,
                'github_account_type' => $metadata['account']['type'] ?? null,
                'repository_selection' => $metadata['repository_selection'] ?? null,
                'permissions' => json_encode($metadata['permissions'] ?? [], JSON_THROW_ON_ERROR),
                'suspended_at' => $metadata['suspended_at'] ?? null,
                'updated_at' => now(),
            ]);

        return DB::table('github_installations')
            ->where('id', $installationRecordId)
            ->first();
    }

    public function markDisconnectedRemotely(
        int $organizationId,
        int $installationRecordId,
        int $actorUserId,
        ?string $ipAddress,
        ?string $userAgent,
    ): object {
        DB::transaction(function () use ($organizationId, $installationRecordId, $actorUserId, $ipAddress, $userAgent): void {
            DB::table('github_installations')
                ->where('id', $installationRecordId)
                ->where('organization_id', $organizationId)
                ->update(['disconnected_at' => now(), 'updated_at' => now()]);

            DB::table('repositories')
                ->where('organization_id', $organizationId)
                ->where('github_installation_id', $installationRecordId)
                ->update(['sync_enabled' => false, 'updated_at' => now()]);

            $this->recordAuditEvent(
                $organizationId,
                $actorUserId,
                'github.disconnected_remotely',
                $installationRecordId,
                [],
                $ipAddress,
                $userAgent,
            );
        });

        return DB::table('github_installations')
            ->where('id', $installationRecordId)
            ->first();
    }

    public function disconnect(
        int $organizationId,
        int $installationRecordId,
        int $actorUserId,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        DB::transaction(function () use ($organizationId, $installationRecordId, $actorUserId, $ipAddress, $userAgent): void {
            DB::table('github_installations')
                ->where('id', $installationRecordId)
                ->where('organization_id', $organizationId)
                ->update(['disconnected_at' => now(), 'updated_at' => now()]);

            DB::table('repositories')
                ->where('organization_id', $organizationId)
                ->where('github_installation_id', $installationRecordId)
                ->update(['sync_enabled' => false, 'updated_at' => now()]);

            $this->recordAuditEvent(
                $organizationId,
                $actorUserId,
                'github.disconnected',
                $installationRecordId,
                [],
                $ipAddress,
                $userAgent,
            );
        });
    }

    public function recordAuditEvent(
        int $organizationId,
        int $actorUserId,
        string $eventType,
        int $installationRecordId,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $now = now();

        DB::table('audit_logs')->insert([
            'organization_id' => $organizationId,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'auditable_type' => 'github_installation',
            'auditable_id' => $installationRecordId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
