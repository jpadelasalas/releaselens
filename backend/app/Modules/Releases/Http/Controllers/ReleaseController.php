<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseChecklistRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Http\Requests\CreateReleaseRequest;
use App\Modules\Releases\Http\Requests\ListReleasesRequest;
use App\Modules\Releases\Http\Requests\ShowReleaseRequest;
use App\Modules\Releases\Http\Requests\TransitionReleaseRequest;
use App\Modules\Releases\Http\Requests\UpdateReleaseRequest;
use App\Modules\Releases\Services\ReleaseService;
use App\Modules\Releases\Support\ReleaseReadiness;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Shared\Support\FeatureFlags;
use Illuminate\Http\JsonResponse;

class ReleaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseService $releaseService,
        private readonly ReleaseChecklistRepositoryInterface $checklist,
        private readonly ReleaseApprovalRepositoryInterface $approvals,
        private readonly DeploymentRepositoryInterface $deployments,
        private readonly FeatureFlags $featureFlags,
    ) {}

    public function index(ListReleasesRequest $request, int $org): JsonResponse
    {
        $paginator = $this->releases->listForOrganization($org, $request->filters(), $request->perPage());

        return $this->successResponse(
            data: collect($paginator->items())->map(
                fn (object $release): array => $this->present($release)
            )->all(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function store(CreateReleaseRequest $request, int $org): JsonResponse
    {
        $release = $this->releaseService->create($org, $request->user()->id, $request->validated());

        return $this->successResponse(data: $this->present($release), status: 201);
    }

    public function show(ShowReleaseRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $pullRequests = $this->releases->pullRequestsForRelease($record->id);
        $repositories = $this->releases->repositoriesForRelease($record->id);
        $checklistItems = $this->checklist->forRelease($record->id);

        return $this->successResponse(data: [
            ...$this->present($record),
            'pull_requests' => $pullRequests
                ->map(fn (object $pr): array => [
                    'id' => (int) $pr->id,
                    'number' => (int) $pr->number,
                    'title' => $pr->title,
                    'html_url' => $pr->html_url,
                    'merged_at' => $pr->merged_at,
                    'repository_id' => (int) $pr->repository_id,
                    'repository_name' => $pr->repository_name,
                ])->all(),
            'repositories' => $repositories
                ->map(fn (object $repository): array => [
                    'id' => (int) $repository->id,
                    'name' => $repository->name,
                    'full_name' => $repository->full_name,
                ])->all(),
            'checklist_items' => $checklistItems
                ->map(fn (object $item): array => [
                    'id' => (int) $item->id,
                    'label' => $item->label,
                    'is_required' => (bool) $item->is_required,
                    'position' => (int) $item->position,
                    'completed_at' => $item->completed_at,
                    'completed_by_user_id' => $item->completed_by_user_id !== null ? (int) $item->completed_by_user_id : null,
                ])->all(),
            'approvals' => $this->approvals->forRelease($record->id)
                ->map(fn (object $approval): array => [
                    'id' => (int) $approval->id,
                    'approver_user_id' => (int) $approval->approver_user_id,
                    'approved_at' => $approval->approved_at,
                ])->all(),
            'readiness_warnings' => ReleaseReadiness::warnings($record, $checklistItems, $pullRequests, $repositories),
            'deployments' => $this->featureFlags->enabled('deployments')
                ? $this->deployments->forRelease($record->id)
                    ->map(fn (object $deployment): array => [
                        'id' => (int) $deployment->id,
                        'repository_name' => $deployment->repository_name,
                        'normalized_environment' => $deployment->normalized_environment,
                        'is_production' => (bool) $deployment->is_production,
                        'status' => $deployment->status,
                        'created_at_github' => $deployment->created_at_github,
                    ])->all()
                : [],
        ]);
    }

    public function update(UpdateReleaseRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $updated = $this->releaseService->update($record, $request->user()->id, $request->validated());

        return $this->successResponse(data: $this->present($updated));
    }

    public function transition(TransitionReleaseRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $updated = $this->releaseService->transition(
            $record,
            $request->user()->id,
            ReleaseState::from($request->validated('to')),
        );

        return $this->successResponse(data: $this->present($updated));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $release): array
    {
        return [
            'id' => (int) $release->id,
            'organization_id' => (int) $release->organization_id,
            'title' => $release->title,
            'description' => $release->description,
            'state' => $release->state,
            'target_release_at' => $release->target_release_at,
            'released_at' => $release->released_at,
            'closed_at' => $release->closed_at,
            'created_by_user_id' => $release->created_by_user_id !== null ? (int) $release->created_by_user_id : null,
            'created_at' => $release->created_at,
            'updated_at' => $release->updated_at,
        ];
    }
}
