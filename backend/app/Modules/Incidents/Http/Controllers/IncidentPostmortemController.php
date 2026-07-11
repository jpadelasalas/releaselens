<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use App\Modules\Incidents\Http\Requests\PublishIncidentPostmortemRequest;
use App\Modules\Incidents\Http\Requests\SaveIncidentPostmortemRequest;
use App\Modules\Incidents\Services\IncidentService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class IncidentPostmortemController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly IncidentRepositoryInterface $incidents,
        private readonly IncidentService $incidentService,
    ) {}

    public function update(SaveIncidentPostmortemRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $postmortem = $this->incidentService->savePostmortem($record, $request->user()->id, $request->validated());

        return $this->successResponse(data: $this->present($postmortem));
    }

    public function publish(PublishIncidentPostmortemRequest $request, int $org, int $incident): JsonResponse
    {
        $record = $this->incidents->findForOrganization($org, $incident);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Incident not found.', 404);
        }

        $postmortem = $this->incidentService->publishPostmortem($record, $request->user()->id);

        return $this->successResponse(data: $this->present($postmortem));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $postmortem): array
    {
        return [
            'summary' => $postmortem->summary,
            'root_cause' => $postmortem->root_cause,
            'impact' => $postmortem->impact,
            'is_published' => (bool) $postmortem->is_published,
            'published_at' => $postmortem->published_at,
        ];
    }
}
