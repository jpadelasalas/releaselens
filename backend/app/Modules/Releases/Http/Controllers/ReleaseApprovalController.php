<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Http\Requests\CreateReleaseApprovalRequest;
use App\Modules\Releases\Services\ReleaseService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReleaseApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseService $releaseService,
    ) {}

    public function store(CreateReleaseApprovalRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $approval = $this->releaseService->approve($record, $request->user()->id);

        return $this->successResponse(data: [
            'id' => (int) $approval->id,
            'approver_user_id' => (int) $approval->approver_user_id,
            'approved_at' => $approval->approved_at,
        ], status: 201);
    }
}
