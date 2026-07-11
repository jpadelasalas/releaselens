<?php

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Contracts\IncidentActionItemRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentLinkRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentTimelineRepositoryInterface;
use App\Modules\Incidents\Contracts\PostmortemRepositoryInterface;
use App\Modules\Incidents\Enums\IncidentSeverity;
use App\Modules\Incidents\Enums\IncidentState;
use App\Modules\Incidents\Exceptions\IncidentRuleException;
use App\Modules\Incidents\Support\IncidentStateMachine;

class IncidentService
{
    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentTimelineRepositoryInterface $timeline,
        private readonly IncidentActionItemRepositoryInterface $actionItems,
        private readonly IncidentLinkRepositoryInterface $links,
        private readonly PostmortemRepositoryInterface $postmortems,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $organizationId, int $actorUserId, array $attributes): object
    {
        $incident = $this->incidents->create($organizationId, [
            ...$attributes,
            'created_by_user_id' => $actorUserId,
        ]);

        $this->timeline->record($incident->id, $actorUserId, 'created', "Incident \"{$incident->title}\" opened.");

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(object $incident, int $actorUserId, array $attributes): object
    {
        $updated = $this->incidents->update($incident->id, $attributes);

        $this->timeline->record($incident->id, $actorUserId, 'updated', 'Incident details updated.');

        return $updated;
    }

    public function transition(object $incident, int $actorUserId, IncidentState $to): object
    {
        $from = IncidentState::from($incident->state);

        if (! IncidentStateMachine::canTransition($from, $to)) {
            throw new IncidentRuleException(
                'INCIDENT_INVALID_TRANSITION',
                "Cannot transition an incident from {$from->value} to {$to->value}.",
                422,
            );
        }

        if ($to === IncidentState::Closed) {
            $severity = IncidentSeverity::from($incident->severity);
            $postmortem = $this->postmortems->find($incident->id);

            if ($severity->requiresPostmortem() && ($postmortem === null || ! $postmortem->is_published)) {
                throw new IncidentRuleException(
                    'INCIDENT_POSTMORTEM_REQUIRED',
                    "A published postmortem is required before a {$severity->value} incident can be closed.",
                    422,
                );
            }
        }

        $extra = match ($to) {
            IncidentState::Resolved => ['resolved_at' => now()],
            IncidentState::Closed => ['closed_at' => now()],
            default => [],
        };

        $this->incidents->updateState($incident->id, $to->value, $extra);

        $this->timeline->record(
            $incident->id,
            $actorUserId,
            'state_changed',
            "State changed from {$from->value} to {$to->value}.",
        );

        return $this->incidents->find($incident->id);
    }

    public function addActionItem(object $incident, int $actorUserId, string $description, ?int $assignedToUserId): object
    {
        $item = $this->actionItems->add($incident->id, $description, $assignedToUserId);

        $this->timeline->record($incident->id, $actorUserId, 'action_item_added', "Action item added: {$description}");

        return $item;
    }

    public function completeActionItem(object $incident, int $actorUserId, int $itemId): object
    {
        $item = $this->actionItems->complete($itemId, $actorUserId);

        $this->timeline->record($incident->id, $actorUserId, 'action_item_completed', 'An action item was completed.');

        return $item;
    }

    public function uncompleteActionItem(object $incident, int $actorUserId, int $itemId): object
    {
        $item = $this->actionItems->uncomplete($itemId);

        $this->timeline->record($incident->id, $actorUserId, 'action_item_reopened', 'An action item was reopened.');

        return $item;
    }

    public function removeActionItem(object $incident, int $actorUserId, int $itemId): void
    {
        $this->actionItems->remove($incident->id, $itemId);

        $this->timeline->record($incident->id, $actorUserId, 'action_item_removed', 'An action item was removed.');
    }

    public function linkEntity(object $incident, int $actorUserId, string $linkableType, int $linkableId): object
    {
        $link = $this->links->link($incident->id, $linkableType, $linkableId);

        $this->timeline->record($incident->id, $actorUserId, 'linked', "Linked to {$linkableType} #{$linkableId}.");

        return $link;
    }

    public function unlinkEntity(object $incident, int $actorUserId, int $linkId): void
    {
        $this->links->remove($incident->id, $linkId);

        $this->timeline->record($incident->id, $actorUserId, 'unlinked', 'A linked entity was removed.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function savePostmortem(object $incident, int $actorUserId, array $attributes): object
    {
        $postmortem = $this->postmortems->upsert($incident->id, [
            ...$attributes,
            'created_by_user_id' => $actorUserId,
        ]);

        $this->timeline->record($incident->id, $actorUserId, 'postmortem_saved', 'Postmortem draft saved.');

        return $postmortem;
    }

    public function publishPostmortem(object $incident, int $actorUserId): object
    {
        $postmortem = $this->postmortems->find($incident->id);

        if ($postmortem === null) {
            throw new IncidentRuleException(
                'INCIDENT_POSTMORTEM_NOT_FOUND',
                'A postmortem draft must exist before it can be published.',
                422,
            );
        }

        $published = $this->postmortems->publish($incident->id);

        $this->timeline->record($incident->id, $actorUserId, 'postmortem_published', 'Postmortem published.');

        return $published;
    }
}
