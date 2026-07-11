<?php

namespace App\Modules\Releases\Services;

use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseActivityRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseChecklistRepositoryInterface;
use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseApprovalMode;
use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Exceptions\ReleaseRuleException;
use App\Modules\Releases\Support\ReleaseStateMachine;

class ReleaseService
{
    /**
     * @var array<int, string>
     */
    private const EDITABLE_STATES = ['draft', 'in_review', 'approved'];

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseActivityRepositoryInterface $activities,
        private readonly ReleaseApprovalRepositoryInterface $approvals,
        private readonly ReleasePolicyRepositoryInterface $policies,
        private readonly ReleaseChecklistRepositoryInterface $checklist,
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
        private readonly NotificationService $notifications,
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
        $this->assertEditable($release);

        $this->releases->update($release->id, $attributes);

        $this->activities->record($release->id, $actorUserId, 'updated', [
            'fields' => array_keys($attributes),
        ]);

        $this->releases->incrementApprovalGeneration($release->id);
        $this->revertApprovalIfNeeded($release, $actorUserId);

        return $this->releases->find($release->id);
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

        if ($to === ReleaseState::Approved && $this->approvalMode($release) === ReleaseApprovalMode::SingleApprover && ! $this->hasValidApproval($release)) {
            throw new ReleaseRuleException(
                'RELEASE_APPROVAL_REQUIRED',
                'This release requires a recorded approval before it can move to Approved.',
                422,
            );
        }

        if ($to === ReleaseState::Released && $this->checklist->hasIncompleteRequiredItems($release->id)) {
            throw new ReleaseRuleException(
                'RELEASE_CHECKLIST_INCOMPLETE',
                'All required checklist items must be completed before a release can be Released.',
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

        $this->notifyOnTransition($release, $to);

        return $this->releases->find($release->id);
    }

    private function notifyOnTransition(object $release, ReleaseState $to): void
    {
        $type = match ($to) {
            ReleaseState::InReview => NotificationType::ReleaseApprovalRequired,
            ReleaseState::Released => NotificationType::ReleaseReleased,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $recipientRoles = $type === NotificationType::ReleaseApprovalRequired
            ? ['owner', 'manager']
            : ['owner', 'manager', 'viewer'];

        $userIds = $this->organizations
            ->membersForOrganization((int) $release->organization_id)
            ->filter(fn (object $member): bool => in_array($member->role, $recipientRoles, true))
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->notifications->notifyUsers(
            organizationId: (int) $release->organization_id,
            userIds: $userIds,
            type: $type->value,
            title: $type === NotificationType::ReleaseApprovalRequired
                ? "\"{$release->title}\" is awaiting approval"
                : "\"{$release->title}\" was released",
            subjectType: 'release',
            subjectId: (int) $release->id,
        );
    }

    public function approve(object $release, int $approverUserId): object
    {
        if ($release->state !== ReleaseState::InReview->value) {
            throw new ReleaseRuleException(
                'RELEASE_NOT_AWAITING_APPROVAL',
                'Only a release In Review can be approved.',
                422,
            );
        }

        $policy = $this->policies->getForOrganization($release->organization_id);
        $allowSelfApproval = $policy !== null && (bool) $policy->allow_self_approval;

        if (! $allowSelfApproval && (int) $release->created_by_user_id === $approverUserId) {
            throw new ReleaseRuleException(
                'RELEASE_SELF_APPROVAL_NOT_ALLOWED',
                'You cannot approve a release you created.',
                422,
            );
        }

        $approval = $this->approvals->record($release->id, $approverUserId, (int) $release->approval_generation);

        $this->activities->record($release->id, $approverUserId, 'approved', []);

        return $approval;
    }

    public function addPullRequest(int $organizationId, object $release, int $actorUserId, int $pullRequestId): object
    {
        $this->assertEditable($release);

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

        $this->releases->incrementApprovalGeneration($release->id);
        $this->revertApprovalIfNeeded($release, $actorUserId);

        return $link;
    }

    public function removePullRequest(object $release, int $actorUserId, int $pullRequestId): void
    {
        $this->assertEditable($release);

        $this->releases->removePullRequest($release->id, $pullRequestId);

        $this->activities->record($release->id, $actorUserId, 'pull_request_removed', [
            'pull_request_id' => $pullRequestId,
        ]);

        $this->releases->incrementApprovalGeneration($release->id);
        $this->revertApprovalIfNeeded($release, $actorUserId);
    }

    private function assertEditable(object $release): void
    {
        if (! in_array($release->state, self::EDITABLE_STATES, true)) {
            throw new ReleaseRuleException(
                'RELEASE_NOT_EDITABLE',
                'A released, closed, or cancelled release cannot be edited.',
                422,
            );
        }
    }

    private function approvalMode(object $release): ReleaseApprovalMode
    {
        $policy = $this->policies->getForOrganization($release->organization_id);

        return $policy !== null
            ? ReleaseApprovalMode::from($policy->approval_mode)
            : ReleaseApprovalMode::SingleApprover;
    }

    private function hasValidApproval(object $release): bool
    {
        return $this->approvals->forRelease($release->id)->contains(
            fn (object $approval): bool => (int) $approval->approval_generation === (int) $release->approval_generation
        );
    }

    private function revertApprovalIfNeeded(object $release, int $actorUserId): void
    {
        if ($release->state !== ReleaseState::Approved->value) {
            return;
        }

        $this->releases->updateState($release->id, ReleaseState::InReview->value);

        $this->activities->record($release->id, $actorUserId, 'state_changed', [
            'from' => ReleaseState::Approved->value,
            'to' => ReleaseState::InReview->value,
            'reason' => 'material_change',
        ]);
    }
}
