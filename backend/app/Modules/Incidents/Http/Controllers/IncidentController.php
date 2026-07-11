<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Contracts\IncidentActionItemRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentLinkRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Contracts\IncidentTimelineRepositoryInterface;
use App\Modules\Incidents\Contracts\PostmortemRepositoryInterface;
use App\Modules\Incidents\Enums\IncidentState;
use App\Modules\Incidents\Http\Requests\CreateIncidentRequest;
use App\Modules\Incidents\Http\Requests\ListIncidentsRequest;
use App\Modules\Incidents\Http\Requests\ShowIncidentRequest;
use App\Modules\Incidents\Http\Requests\TransitionIncidentRequest;
use App\Modules\Incidents\Http\Requests\UpdateIncidentRequest;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncidentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentService $incidentService,
        private readonly IncidentTimelineRepositoryInterface $timeline,
        private readonly IncidentActionItemRepositoryInterface $actionItems,
        private readonly IncidentLinkRepositoryInterface $links,
        private readonly PostmortemRepositoryInterface $postmortems,
    ) {}

    public function index(ListIncidentsRequest $request, int $org): JsonResponse
    {
        $paginator = $this->incidents->listForOrganization($org, $request->filters(), $request->perPage());

        return $this->successResponse(
            data: collect($paginator->items())->map(fn (object $incident): array => $this->present($incident))->all(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(CreateIncidentRequest $request, int $org): JsonResponse
    {
        $incident = $this->incidentService->create($org, $request->user()->id, $request->validated());

        return $this->successResponse(data: $this->present($incident), status: 201);
    }

    public function show(ShowIncidentRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $postmortem = $this->postmortems->find($record->id);

        return $this->successResponse(data: [
            ...$this->present($record),
            'timeline' => $this->timeline->forIncident($record->id)
                ->map(fn (object $entry): array => [
                    'id' => (int) $entry->id,
                    'actor_user_id' => $entry->actor_user_id !== null ? (int) $entry->actor_user_id : null,
                    'entry_type' => $entry->entry_type,
                    'message' => $entry->message,
                    'occurred_at' => $entry->occurred_at,
                ])->all(),
            'action_items' => $this->actionItems->forIncident($record->id)
                ->map(fn (object $item): array => [
                    'id' => (int) $item->id,
                    'description' => $item->description,
                    'assigned_to_user_id' => $item->assigned_to_user_id !== null ? (int) $item->assigned_to_user_id : null,
                    'is_completed' => (bool) $item->is_completed,
                    'completed_at' => $item->completed_at,
                    'completed_by_user_id' => $item->completed_by_user_id !== null ? (int) $item->completed_by_user_id : null,
                ])->all(),
            'links' => $this->links->forIncident($record->id)
                ->map(fn (object $link): array => [
                    'id' => (int) $link->id,
                    'linkable_type' => $link->linkable_type,
                    'linkable_id' => (int) $link->linkable_id,
                ])->all(),
            'postmortem' => $postmortem !== null ? [
                'summary' => $postmortem->summary,
                'root_cause' => $postmortem->root_cause,
                'impact' => $postmortem->impact,
                'is_published' => (bool) $postmortem->is_published,
                'published_at' => $postmortem->published_at,
            ] : null,
        ]);
    }

    public function update(UpdateIncidentRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $updated = $this->incidentService->update($record, $request->user()->id, $request->validated());

        return $this->successResponse(data: $this->present($updated));
    }

    public function transition(TransitionIncidentRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $updated = $this->incidentService->transition(
            $record,
            $request->user()->id,
            IncidentState::from($request->validated('to')),
        );

        return $this->successResponse(data: $this->present($updated));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $incident): array
    {
        return [
            'id' => (int) $incident->id,
            'organization_id' => (int) $incident->organization_id,
            'title' => $incident->title,
            'summary' => $incident->summary,
            'severity' => $incident->severity,
            'state' => $incident->state,
            'started_at' => $incident->started_at,
            'resolved_at' => $incident->resolved_at,
            'closed_at' => $incident->closed_at,
            'created_by_user_id' => $incident->created_by_user_id !== null ? (int) $incident->created_by_user_id : null,
            'created_at' => $incident->created_at,
            'updated_at' => $incident->updated_at,
        ];
    }
}
