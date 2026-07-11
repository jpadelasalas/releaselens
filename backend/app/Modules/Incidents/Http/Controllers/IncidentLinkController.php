<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Http\Requests\LinkIncidentEntityRequest;
use App\Modules\Incidents\Http\Requests\UnlinkIncidentEntityRequest;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncidentLinkController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentService $incidentService,
    ) {}

    public function store(LinkIncidentEntityRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $link = $this->incidentService->linkEntity(
            $record,
            $request->user()->id,
            (string) $request->validated('linkable_type'),
            (int) $request->validated('linkable_id'),
        );

        return $this->successResponse(data: [
            'id' => (int) $link->id,
            'linkable_type' => $link->linkable_type,
            'linkable_id' => (int) $link->linkable_id,
        ], status: 201);
    }

    public function destroy(UnlinkIncidentEntityRequest $request, int $org, int $incident, int $link): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $this->incidentService->unlinkEntity($record, $request->user()->id, $link);

        return $this->successResponse(data: ['status' => 'removed']);
    }
}
