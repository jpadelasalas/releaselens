<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Http\Requests\AddIncidentActionItemRequest;
use App\Modules\Incidents\Http\Requests\RemoveIncidentActionItemRequest;
use App\Modules\Incidents\Http\Requests\UpdateIncidentActionItemRequest;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncidentActionItemController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentService $incidentService,
    ) {}

    public function store(AddIncidentActionItemRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $item = $this->incidentService->addActionItem(
            $record,
            $request->user()->id,
            (string) $request->validated('description'),
            $request->validated('assigned_to_user_id') !== null ? (int) $request->validated('assigned_to_user_id') : null,
        );

        return $this->successResponse(data: $this->present($item), status: 201);
    }

    public function update(UpdateIncidentActionItemRequest $request, int $org, int $incident, int $item): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $updated = $request->validated('completed')
            ? $this->incidentService->completeActionItem($record, $request->user()->id, $item)
            : $this->incidentService->uncompleteActionItem($record, $request->user()->id, $item);

        return $this->successResponse(data: $this->present($updated));
    }

    public function destroy(RemoveIncidentActionItemRequest $request, int $org, int $incident, int $item): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $this->incidentService->removeActionItem($record, $request->user()->id, $item);

        return $this->successResponse(data: ['status' => 'removed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'description' => $item->description,
            'assigned_to_user_id' => $item->assigned_to_user_id !== null ? (int) $item->assigned_to_user_id : null,
            'is_completed' => (bool) $item->is_completed,
            'completed_at' => $item->completed_at,
            'completed_by_user_id' => $item->completed_by_user_id !== null ? (int) $item->completed_by_user_id : null,
        ];
    }
}
