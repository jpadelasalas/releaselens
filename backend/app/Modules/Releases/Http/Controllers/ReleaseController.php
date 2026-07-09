<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Http\Requests\CreateReleaseRequest;
use App\Modules\Releases\Http\Requests\ListReleasesRequest;
use App\Modules\Releases\Http\Requests\ShowReleaseRequest;
use App\Modules\Releases\Http\Requests\TransitionReleaseRequest;
use App\Modules\Releases\Http\Requests\UpdateReleaseRequest;
use App\Modules\Releases\Services\ReleaseService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReleaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseService $releaseService,
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

        return $this->successResponse(data: [
            ...$this->present($record),
            'pull_requests' => $this->releases->pullRequestsForRelease($record->id)
                ->map(fn (object $pr): array => [
                    'id' => (int) $pr->id,
                    'number' => (int) $pr->number,
                    'title' => $pr->title,
                    'html_url' => $pr->html_url,
                    'merged_at' => $pr->merged_at,
                    'repository_id' => (int) $pr->repository_id,
                    'repository_name' => $pr->repository_name,
                ])->all(),
            'repositories' => $this->releases->repositoriesForRelease($record->id)
                ->map(fn (object $repository): array => [
                    'id' => (int) $repository->id,
                    'name' => $repository->name,
                    'full_name' => $repository->full_name,
                ])->all(),
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
