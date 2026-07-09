<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleaseChecklistRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Http\Requests\CreateReleaseChecklistItemRequest;
use App\Modules\Releases\Http\Requests\RemoveReleaseChecklistItemRequest;
use App\Modules\Releases\Http\Requests\UpdateReleaseChecklistItemRequest;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReleaseChecklistItemController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseChecklistRepositoryInterface $checklist,
    ) {}

    public function store(CreateReleaseChecklistItemRequest $request, int $org, int $release): JsonResponse
    {
        if ($this->releases->findForOrganization($org, $release) === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $item = $this->checklist->add(
            $release,
            (string) $request->validated('label'),
            (bool) ($request->validated('is_required') ?? true),
        );

        return $this->successResponse(data: $this->present($item), status: 201);
    }

    public function update(UpdateReleaseChecklistItemRequest $request, int $org, int $release, int $item): JsonResponse
    {
        if ($this->releases->findForOrganization($org, $release) === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        if ($this->checklist->find($release, $item) === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Checklist item not found.', 404);
        }

        $updated = $request->validated('completed')
            ? $this->checklist->complete($item, $request->user()->id)
            : $this->checklist->uncomplete($item);

        return $this->successResponse(data: $this->present($updated));
    }

    public function destroy(RemoveReleaseChecklistItemRequest $request, int $org, int $release, int $item): JsonResponse
    {
        if ($this->releases->findForOrganization($org, $release) === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $this->checklist->remove($release, $item);

        return $this->successResponse(data: ['status' => 'removed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'label' => $item->label,
            'is_required' => (bool) $item->is_required,
            'position' => (int) $item->position,
            'completed_at' => $item->completed_at,
            'completed_by_user_id' => $item->completed_by_user_id !== null ? (int) $item->completed_by_user_id : null,
        ];
    }
}
