<?php

namespace App\Modules\Releases\Repositories;

use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReleaseRepository implements ReleaseRepositoryInterface
{
    public function create(int $organizationId, array $attributes): object
    {
        $now = now();
        $id = (int) DB::table('releases')->insertGetId([
            'organization_id' => $organizationId,
            'state' => ReleaseState::Draft->value,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('releases')->find($id);
    }

    public function find(int $id): ?object
    {
        return DB::table('releases')->find($id);
    }

    public function findForOrganization(int $organizationId, int $id): ?object
    {
        return DB::table('releases')
            ->where('organization_id', $organizationId)
            ->where('id', $id)
            ->first();
    }

    public function listForOrganization(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('releases')->where('organization_id', $organizationId);

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function update(int $id, array $attributes): object
    {
        DB::table('releases')->where('id', $id)->update([
            ...$attributes,
            'updated_at' => now(),
        ]);

        return DB::table('releases')->find($id);
    }

    public function updateState(int $id, string $state, array $extra = []): void
    {
        DB::table('releases')->where('id', $id)->update([
            ...$extra,
            'state' => $state,
            'updated_at' => now(),
        ]);
    }

    public function addPullRequest(int $releaseId, int $pullRequestId, ?int $addedByUserId): object
    {
        $now = now();
        $pullRequest = DB::table('pull_requests')->find($pullRequestId);

        DB::table('release_repositories')->insertOrIgnore([
            'release_id' => $releaseId,
            'repository_id' => $pullRequest->repository_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::table('release_pull_requests')->insertGetId([
            'release_id' => $releaseId,
            'pull_request_id' => $pullRequestId,
            'added_by_user_id' => $addedByUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('release_pull_requests')->find($id);
    }

    public function removePullRequest(int $releaseId, int $pullRequestId): void
    {
        DB::table('release_pull_requests')
            ->where('release_id', $releaseId)
            ->where('pull_request_id', $pullRequestId)
            ->delete();
    }

    public function pullRequestsForRelease(int $releaseId): Collection
    {
        return DB::table('release_pull_requests')
            ->join('pull_requests', 'pull_requests.id', '=', 'release_pull_requests.pull_request_id')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->where('release_pull_requests.release_id', $releaseId)
            ->orderBy('pull_requests.merged_at')
            ->get([
                'pull_requests.id',
                'pull_requests.number',
                'pull_requests.title',
                'pull_requests.html_url',
                'pull_requests.merged_at',
                'repositories.id as repository_id',
                'repositories.name as repository_name',
            ]);
    }

    public function repositoriesForRelease(int $releaseId): Collection
    {
        return DB::table('release_repositories')
            ->join('repositories', 'repositories.id', '=', 'release_repositories.repository_id')
            ->where('release_repositories.release_id', $releaseId)
            ->get(['repositories.id', 'repositories.name', 'repositories.full_name']);
    }

    public function findMergedPullRequestForOrganization(int $organizationId, int $pullRequestId): ?object
    {
        return DB::table('pull_requests')
            ->join('repositories', 'repositories.id', '=', 'pull_requests.repository_id')
            ->where('repositories.organization_id', $organizationId)
            ->where('pull_requests.id', $pullRequestId)
            ->whereNotNull('pull_requests.merged_at')
            ->select('pull_requests.*')
            ->first();
    }

    public function findPullRequestInRelease(int $releaseId, int $pullRequestId): ?object
    {
        return DB::table('release_pull_requests')
            ->where('release_id', $releaseId)
            ->where('pull_request_id', $pullRequestId)
            ->first();
    }
}
