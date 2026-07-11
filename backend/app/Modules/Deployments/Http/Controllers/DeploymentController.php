<?php

namespace App\Modules\Deployments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Deployments\Http\Requests\ListDeploymentsRequest;
use App\Modules\Deployments\Http\Requests\ShowDeploymentRequest;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeploymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DeploymentRepositoryInterface $deployments,
    ) {}

    public function index(ListDeploymentsRequest $request, int $org): JsonResponse
    {
        $paginator = $this->deployments->listForOrganization($org, $request->filters(), $request->perPage());

        return $this->successResponse(
            data: collect($paginator->items())->map(fn (object $deployment): array => $this->present($deployment))->all(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function show(ShowDeploymentRequest $request, int $org, int $deployment): JsonResponse
    {
        $record = $this->deployments->findForOrganization($org, $deployment);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Deployment not found.', 404);
        }

        return $this->successResponse(data: [
            ...$this->present($record),
            'status_events' => $this->deployments->statusEventsForDeployment($record->id)
                ->map(fn (object $event): array => [
                    'id' => (int) $event->id,
                    'status' => $event->status,
                    'original_status' => $event->original_status,
                    'description' => $event->description,
                    'log_url' => $event->log_url,
                    'environment_url' => $event->environment_url,
                    'occurred_at' => $event->occurred_at,
                ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $deployment): array
    {
        return [
            'id' => (int) $deployment->id,
            'repository_id' => (int) $deployment->repository_id,
            'repository_name' => $deployment->repository_name,
            'release_id' => $deployment->release_id !== null ? (int) $deployment->release_id : null,
            'ref' => $deployment->ref,
            'sha' => $deployment->sha,
            'original_environment' => $deployment->original_environment,
            'normalized_environment' => $deployment->normalized_environment,
            'is_production' => (bool) $deployment->is_production,
            'status' => $deployment->status,
            'original_status' => $deployment->original_status,
            'description' => $deployment->description,
            'created_at_github' => $deployment->created_at_github,
            'updated_at_github' => $deployment->updated_at_github,
        ];
    }
}
