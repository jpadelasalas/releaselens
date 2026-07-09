<?php

namespace App\Modules\Releases\Services;

use App\Modules\Releases\Contracts\ReleaseActivityRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Exceptions\ReleaseRuleException;
use App\Modules\Releases\Support\ReleaseStateMachine;

class ReleaseService
{
    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseActivityRepositoryInterface $activities,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $organizationId, int $actorUserId, array $attributes): object
    {
        $release = $this->releases->create($organizationId, [
            ...$attributes,
            'created_by_user_id' => $actorUserId,
        ]);

        $this->activities->record($release->id, $actorUserId, 'created', [
            'title' => $release->title,
        ]);

        return $release;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(object $release, int $actorUserId, array $attributes): object
    {
        if (in_array($release->state, [ReleaseState::Closed->value, ReleaseState::Cancelled->value], true)) {
            throw new ReleaseRuleException(
                'RELEASE_NOT_EDITABLE',
                'A closed or cancelled release cannot be edited.',
                422,
            );
        }

        $updated = $this->releases->update($release->id, $attributes);

        $this->activities->record($release->id, $actorUserId, 'updated', [
            'fields' => array_keys($attributes),
        ]);

        return $updated;
    }

    public function transition(object $release, int $actorUserId, ReleaseState $to): object
    {
        $from = ReleaseState::from($release->state);

        if (! ReleaseStateMachine::canTransition($from, $to)) {
            throw new ReleaseRuleException(
                'RELEASE_INVALID_TRANSITION',
                "Cannot transition a release from {$from->value} to {$to->value}.",
                422,
            );
        }

        $extra = match ($to) {
            ReleaseState::Released => ['released_at' => now()],
            ReleaseState::Closed => ['closed_at' => now()],
            default => [],
        };

        $this->releases->updateState($release->id, $to->value, $extra);

        $this->activities->record($release->id, $actorUserId, 'state_changed', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $this->releases->find($release->id);
    }

    public function addPullRequest(int $organizationId, object $release, int $actorUserId, int $pullRequestId): object
    {
        $pullRequest = $this->releases->findMergedPullRequestForOrganization($organizationId, $pullRequestId);

        if ($pullRequest === null) {
            throw new ReleaseRuleException(
                'RELEASE_PULL_REQUEST_NOT_ELIGIBLE',
                'Only merged pull requests from this organization can be added to a release.',
                422,
            );
        }

        if ($this->releases->findPullRequestInRelease($release->id, $pullRequestId) !== null) {
            throw new ReleaseRuleException(
                'RELEASE_PULL_REQUEST_ALREADY_INCLUDED',
                'This pull request is already included in the release.',
                422,
            );
        }

        $link = $this->releases->addPullRequest($release->id, $pullRequestId, $actorUserId);

        $this->activities->record($release->id, $actorUserId, 'pull_request_added', [
            'pull_request_id' => $pullRequestId,
        ]);

        return $link;
    }

    public function removePullRequest(object $release, int $actorUserId, int $pullRequestId): void
    {
        $this->releases->removePullRequest($release->id, $pullRequestId);

        $this->activities->record($release->id, $actorUserId, 'pull_request_removed', [
            'pull_request_id' => $pullRequestId,
        ]);
    }
}
