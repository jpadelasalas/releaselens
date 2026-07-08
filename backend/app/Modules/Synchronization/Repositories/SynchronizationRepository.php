<?php

namespace App\Modules\Synchronization\Repositories;

use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SynchronizationRepository implements SynchronizationRepositoryInterface
{
    public function repositoryForOrganization(int $organizationId, int $repositoryId): ?object
    {
        return DB::table('repositories')
            ->leftJoin('github_installations', 'github_installations.id', '=', 'repositories.github_installation_id')
            ->where('repositories.organization_id', $organizationId)
            ->where('repositories.id', $repositoryId)
            ->select([
                'repositories.*',
                'github_installations.github_installation_id as external_installation_id',
                'github_installations.suspended_at as installation_suspended_at',
                'github_installations.disconnected_at as installation_disconnected_at',
            ])
            ->first();
    }

    public function createOrGetActiveRun(
        int $organizationId,
        int $repositoryId,
        ?int $actorUserId,
        string $triggerType = 'manual',
    ): array {
        return DB::transaction(function () use ($organizationId, $repositoryId, $actorUserId, $triggerType): array {
            DB::table('repositories')
                ->where('organization_id', $organizationId)
                ->where('id', $repositoryId)
                ->lockForUpdate()
                ->first();
            $active = DB::table('sync_runs')
                ->where('repository_id', $repositoryId)
                ->whereIn('status', ['queued', 'running'])
                ->oldest('id')
                ->first();

            if ($active !== null) {
                return ['run' => $active, 'created' => false];
            }

            $now = now();
            $runId = (int) DB::table('sync_runs')->insertGetId([
                'repository_id' => $repositoryId,
                'trigger_type' => $triggerType,
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('repositories')->where('id', $repositoryId)->update([
                'sync_status' => 'queued',
                'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'organization_id' => $organizationId,
                'actor_user_id' => $actorUserId,
                'event_type' => 'sync.requested',
                'auditable_type' => 'sync_run',
                'auditable_id' => $runId,
                'metadata' => json_encode(['repository_id' => $repositoryId], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'run' => DB::table('sync_runs')->find($runId),
                'created' => true,
            ];
        });
    }

    public function repositoryByGitHubId(int $githubRepositoryId): ?object
    {
        return DB::table('repositories')
            ->where('github_repository_id', $githubRepositoryId)
            ->first();
    }

    public function upsertPullRequestFromWebhook(int $repositoryId, array $pullRequestPayload): ?object
    {
        $githubId = (int) ($pullRequestPayload['id'] ?? 0);

        if ($githubId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($repositoryId, $githubId, $pullRequestPayload): ?object {
            // Shared with complete(): locking the repository row here
            // serializes this webhook upsert against a concurrent bulk
            // sync/reconciliation run for the same repository.
            DB::table('repositories')->where('id', $repositoryId)->lockForUpdate()->first();

            $authorId = $this->upsertGitHubUser($pullRequestPayload['user'] ?? null);
            $values = [
                'repository_id' => $repositoryId,
                'number' => (int) $pullRequestPayload['number'],
                'title' => (string) $pullRequestPayload['title'],
                'html_url' => $pullRequestPayload['html_url'] ?? null,
                'state' => (string) $pullRequestPayload['state'],
                'is_draft' => (bool) ($pullRequestPayload['draft'] ?? false),
                'author_github_user_id' => $authorId,
                'base_ref' => (string) ($pullRequestPayload['base']['ref'] ?? ''),
                'head_ref' => (string) ($pullRequestPayload['head']['ref'] ?? ''),
                'additions' => (int) ($pullRequestPayload['additions'] ?? 0),
                'deletions' => (int) ($pullRequestPayload['deletions'] ?? 0),
                'changed_files' => (int) ($pullRequestPayload['changed_files'] ?? 0),
                'commits_count' => (int) ($pullRequestPayload['commits'] ?? 0),
                'comments_count' => (int) ($pullRequestPayload['comments'] ?? 0),
                'created_at_github' => $pullRequestPayload['created_at'],
                'updated_at_github' => $pullRequestPayload['updated_at'] ?? null,
                'closed_at' => $pullRequestPayload['closed_at'] ?? null,
                'merged_at' => $pullRequestPayload['merged_at'] ?? null,
                'updated_at' => now(),
            ];
            $existing = DB::table('pull_requests')->where('github_pull_request_id', $githubId)->first();

            if ($existing === null) {
                $pullRequestId = (int) DB::table('pull_requests')->insertGetId([
                    'github_pull_request_id' => $githubId,
                    ...$values,
                    'created_at' => now(),
                ]);
            } else {
                $pullRequestId = (int) $existing->id;

                if ($this->isAtLeastAsRecent($existing->updated_at_github, $pullRequestPayload)) {
                    DB::table('pull_requests')->where('id', $pullRequestId)->update($values);
                }
            }

            return DB::table('pull_requests')->find($pullRequestId);
        });
    }

    public function upsertReviewFromWebhook(int $pullRequestId, array $reviewPayload): void
    {
        $githubId = (int) ($reviewPayload['id'] ?? 0);

        if ($githubId <= 0) {
            return;
        }

        $values = [
            'pull_request_id' => $pullRequestId,
            'reviewer_github_user_id' => $this->upsertGitHubUser($reviewPayload['user'] ?? null),
            'state' => strtolower((string) ($reviewPayload['state'] ?? 'commented')),
            'submitted_at' => $reviewPayload['submitted_at'] ?? null,
            'github_updated_at' => $reviewPayload['submitted_at'] ?? null,
            'updated_at' => now(),
        ];
        $existing = DB::table('pull_request_reviews')->where('github_review_id', $githubId)->first();

        if ($existing === null) {
            DB::table('pull_request_reviews')->insert([
                'github_review_id' => $githubId,
                ...$values,
                'created_at' => now(),
            ]);

            return;
        }

        DB::table('pull_request_reviews')->where('id', $existing->id)->update($values);
    }

    public function updateRepositoryMetadataFromWebhook(int $repositoryId, array $repositoryPayload): void
    {
        $values = ['updated_at' => now()];

        if (isset($repositoryPayload['name'])) {
            $values['name'] = (string) $repositoryPayload['name'];
        }

        if (isset($repositoryPayload['full_name'])) {
            $values['full_name'] = (string) $repositoryPayload['full_name'];
        }

        if (array_key_exists('archived', $repositoryPayload)) {
            $values['is_archived'] = (bool) $repositoryPayload['archived'];
        }

        DB::table('repositories')->where('id', $repositoryId)->update($values);
    }

    public function markRepositoryAccessibility(int $repositoryId, bool $isAccessible, ?string $accessError): void
    {
        DB::table('repositories')->where('id', $repositoryId)->update([
            'is_accessible' => $isAccessible,
            'access_error' => $accessError,
            'updated_at' => now(),
        ]);
    }

    public function scheduledCandidates(): Collection
    {
        return DB::table('repositories')
            ->join('github_installations', 'github_installations.id', '=', 'repositories.github_installation_id')
            ->where('repositories.sync_enabled', true)
            ->whereNull('github_installations.suspended_at')
            ->whereNull('github_installations.disconnected_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('sync_runs')
                    ->whereColumn('sync_runs.repository_id', 'repositories.id')
                    ->whereIn('sync_runs.status', ['queued', 'running']);
            })
            ->get([
                'repositories.id as repository_id',
                'repositories.organization_id',
            ]);
    }

    public function contextForRun(int $runId): ?object
    {
        return DB::table('sync_runs')
            ->join('repositories', 'repositories.id', '=', 'sync_runs.repository_id')
            ->join('github_installations', 'github_installations.id', '=', 'repositories.github_installation_id')
            ->where('sync_runs.id', $runId)
            ->select([
                'sync_runs.id as run_id',
                'sync_runs.cursor_before as previous_cursor',
                'sync_runs.status as run_status',
                'repositories.id as repository_id',
                'repositories.organization_id',
                'repositories.full_name',
                'repositories.sync_enabled',
                'repositories.last_successful_sync_at',
                'github_installations.github_installation_id',
                'github_installations.suspended_at',
                'github_installations.disconnected_at',
            ])
            ->first();
    }

    public function markRunning(int $runId): void
    {
        $run = DB::table('sync_runs')->find($runId);

        if ($run === null) {
            return;
        }

        $now = now();
        DB::table('sync_runs')->where('id', $runId)->update([
            'status' => 'running',
            'started_at' => $now,
            'cursor_before' => $this->latestSuccessfulCursor((int) $run->repository_id),
            'updated_at' => $now,
        ]);
        DB::table('repositories')->where('id', $run->repository_id)->update([
            'sync_status' => 'running',
            'last_sync_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function complete(int $runId, array $result): void
    {
        DB::transaction(function () use ($runId, $result): void {
            $run = DB::table('sync_runs')->find($runId);

            if ($run === null) {
                return;
            }

            // Shared with upsertPullRequestFromWebhook: locking the repository
            // row here serializes a bulk sync/reconciliation run against
            // concurrent webhook processing for the same repository.
            DB::table('repositories')->where('id', $run->repository_id)->lockForUpdate()->first();

            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unsupported' => 0];

            foreach ($result['items'] as $item) {
                $this->upsertPullRequest((int) $run->repository_id, $item, $counts);
            }

            $now = now();
            $repositoryMetadata = $result['repository'] ?? [];
            DB::table('sync_runs')->where('id', $runId)->update([
                'status' => 'success',
                'completed_at' => $now,
                'cursor_after' => $result['cursor_after'],
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'unchanged_count' => $counts['unchanged'],
                'unsupported_count' => $counts['unsupported'],
                'rate_limit_remaining' => $result['rate_limit_remaining'],
                'rate_limit_reset_at' => $result['rate_limit_reset_at'],
                'updated_at' => $now,
            ]);
            $repositoryValues = [
                'sync_status' => 'success',
                'last_sync_at' => $now,
                'last_successful_sync_at' => $now,
                'is_accessible' => true,
                'access_error' => null,
                'updated_at' => $now,
            ];

            if (is_array($repositoryMetadata) && isset($repositoryMetadata['id'])) {
                $repositoryValues = [
                    ...$repositoryValues,
                    'name' => $repositoryMetadata['name'],
                    'full_name' => $repositoryMetadata['full_name'],
                    'description' => $repositoryMetadata['description'] ?? null,
                    'visibility' => $repositoryMetadata['visibility']
                        ?? (($repositoryMetadata['private'] ?? false) ? 'private' : 'public'),
                    'default_branch' => $repositoryMetadata['default_branch'] ?? null,
                    'html_url' => $repositoryMetadata['html_url'] ?? null,
                    'is_archived' => (bool) ($repositoryMetadata['archived'] ?? false),
                ];
            }

            DB::table('repositories')->where('id', $run->repository_id)->update($repositoryValues);
        });
    }

    public function fail(
        int $runId,
        string $category,
        string $summary,
        string $status = 'failed',
    ): void {
        DB::transaction(function () use ($runId, $category, $summary, $status): void {
            $run = DB::table('sync_runs')->find($runId);

            if ($run === null || in_array($run->status, ['success', 'failed', 'deferred'], true)) {
                return;
            }

            $now = now();
            $isInaccessibleCategory = in_array($category, [
                'not_found',
                'authentication',
                'permission',
                'GITHUB_INSTALLATION_NOT_FOUND',
            ], true);
            DB::table('sync_runs')->where('id', $runId)->update([
                'status' => $status,
                'completed_at' => $now,
                'failed_count' => 1,
                'inaccessible_count' => $isInaccessibleCategory ? 1 : 0,
                'error_category' => $category,
                'error_summary' => $summary,
                'updated_at' => $now,
            ]);
            DB::table('sync_run_errors')->insert([
                'sync_run_id' => $runId,
                'category' => $category,
                'message' => $summary,
                'safe_context' => json_encode([], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $repositoryValues = [
                'sync_status' => $status,
                'last_sync_at' => $now,
                'updated_at' => $now,
            ];

            if ($isInaccessibleCategory) {
                $repositoryValues['is_accessible'] = false;
                $repositoryValues['access_error'] = $category;
            }

            DB::table('repositories')->where('id', $run->repository_id)->update($repositoryValues);
        });
    }

    public function recentRuns(int $organizationId, int $repositoryId, int $limit): Collection
    {
        return DB::table('sync_runs')
            ->join('repositories', 'repositories.id', '=', 'sync_runs.repository_id')
            ->where('repositories.organization_id', $organizationId)
            ->where('repositories.id', $repositoryId)
            ->latest('sync_runs.id')
            ->limit($limit)
            ->get(['sync_runs.*']);
    }

    /** @param array<string, int> $counts */
    private function upsertPullRequest(int $repositoryId, array $item, array &$counts): void
    {
        $pullRequest = $item['pull_request'];
        $githubId = (int) ($pullRequest['id'] ?? 0);

        if ($githubId <= 0) {
            $counts['unsupported']++;

            return;
        }

        $authorId = $this->upsertGitHubUser($pullRequest['user'] ?? null);
        $values = [
            'repository_id' => $repositoryId,
            'number' => (int) $pullRequest['number'],
            'title' => (string) $pullRequest['title'],
            'html_url' => $pullRequest['html_url'] ?? null,
            'state' => (string) $pullRequest['state'],
            'is_draft' => (bool) ($pullRequest['draft'] ?? false),
            'author_github_user_id' => $authorId,
            'base_ref' => (string) ($pullRequest['base']['ref'] ?? ''),
            'head_ref' => (string) ($pullRequest['head']['ref'] ?? ''),
            'additions' => (int) ($pullRequest['additions'] ?? 0),
            'deletions' => (int) ($pullRequest['deletions'] ?? 0),
            'changed_files' => (int) ($pullRequest['changed_files'] ?? 0),
            'commits_count' => (int) ($pullRequest['commits'] ?? 0),
            'comments_count' => (int) ($pullRequest['comments'] ?? 0),
            'created_at_github' => $pullRequest['created_at'],
            'updated_at_github' => $pullRequest['updated_at'] ?? null,
            'closed_at' => $pullRequest['closed_at'] ?? null,
            'merged_at' => $pullRequest['merged_at'] ?? null,
            'updated_at' => now(),
        ];
        $existing = DB::table('pull_requests')->where('github_pull_request_id', $githubId)->first();

        if ($existing === null) {
            $pullRequestId = (int) DB::table('pull_requests')->insertGetId([
                'github_pull_request_id' => $githubId,
                ...$values,
                'created_at' => now(),
            ]);
            $counts['created']++;
        } else {
            $pullRequestId = (int) $existing->id;

            // Shares the same recency guard as upsertPullRequestFromWebhook so
            // a bulk sync/reconciliation run using possibly-stale fetched data
            // can never regress a state a webhook already applied more recently.
            if (
                $this->recordChanged($existing, $values) &&
                $this->isAtLeastAsRecent($existing->updated_at_github, $pullRequest)
            ) {
                DB::table('pull_requests')->where('id', $pullRequestId)->update($values);
                $counts['updated']++;
            } else {
                $counts['unchanged']++;
            }
        }

        foreach ($item['reviews'] as $review) {
            $this->upsertReview($pullRequestId, $review, $counts);
        }
    }

    /** @param array<string, int> $counts */
    private function upsertReview(int $pullRequestId, array $review, array &$counts): void
    {
        $githubId = (int) ($review['id'] ?? 0);

        if ($githubId <= 0) {
            return;
        }

        $values = [
            'pull_request_id' => $pullRequestId,
            'reviewer_github_user_id' => $this->upsertGitHubUser($review['user'] ?? null),
            'state' => strtolower((string) ($review['state'] ?? 'commented')),
            'submitted_at' => $review['submitted_at'] ?? null,
            'github_updated_at' => $review['submitted_at'] ?? null,
            'updated_at' => now(),
        ];
        $existing = DB::table('pull_request_reviews')->where('github_review_id', $githubId)->first();

        if ($existing === null) {
            DB::table('pull_request_reviews')->insert([
                'github_review_id' => $githubId,
                ...$values,
                'created_at' => now(),
            ]);
            $counts['created']++;
        } elseif ($this->recordChanged($existing, $values)) {
            DB::table('pull_request_reviews')->where('id', $existing->id)->update($values);
            $counts['updated']++;
        } else {
            $counts['unchanged']++;
        }
    }

    private function upsertGitHubUser(mixed $user): ?int
    {
        if (! is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            return null;
        }

        $githubId = (int) $user['id'];
        $login = (string) ($user['login'] ?? "github-user-{$githubId}");
        $values = [
            'login' => $login,
            'type' => (string) ($user['type'] ?? 'User'),
            'account_type' => $user['type'] ?? null,
            'is_bot' => ($user['type'] ?? null) === 'Bot' || str_ends_with($login, '[bot]'),
            'avatar_url' => $user['avatar_url'] ?? null,
            'updated_at' => now(),
        ];
        $existing = DB::table('github_users')->where('github_user_id', $githubId)->first();

        if ($existing === null) {
            DB::table('github_users')->insert([
                'github_user_id' => $githubId,
                ...$values,
                'created_at' => now(),
            ]);
        } elseif ($this->recordChanged($existing, $values)) {
            DB::table('github_users')->where('id', $existing->id)->update($values);
        }

        return (int) DB::table('github_users')
            ->where('github_user_id', $githubId)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $incomingPayload
     */
    private function isAtLeastAsRecent(mixed $storedUpdatedAt, array $incomingPayload): bool
    {
        $incomingUpdatedAt = $incomingPayload['updated_at'] ?? $incomingPayload['created_at'] ?? null;

        if ($incomingUpdatedAt === null || $storedUpdatedAt === null) {
            return true;
        }

        $incoming = CarbonImmutable::parse($incomingUpdatedAt)->utc();
        $stored = CarbonImmutable::parse($storedUpdatedAt)->utc();

        return ! $incoming->lessThan($stored);
    }

    private function latestSuccessfulCursor(int $repositoryId): ?string
    {
        return DB::table('sync_runs')
            ->where('repository_id', $repositoryId)
            ->where('status', 'success')
            ->latest('id')
            ->value('cursor_after');
    }

    /** @param array<string, mixed> $values */
    private function recordChanged(object $existing, array $values): bool
    {
        foreach ($values as $field => $expected) {
            if ($field === 'updated_at') {
                continue;
            }

            $actual = $existing->{$field} ?? null;

            if ($this->comparable($actual) !== $this->comparable($expected)) {
                return true;
            }
        }

        return false;
    }

    private function comparable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc()->toIso8601String();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $value) === 1) {
            return CarbonImmutable::parse($value)->utc()->toIso8601String();
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        return $value;
    }
}
