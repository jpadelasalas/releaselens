<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Http\Requests\AddReleasePullRequestRequest;
use App\Modules\Releases\Http\Requests\RemoveReleasePullRequestRequest;
use App\Modules\Releases\Services\ReleaseService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReleasePullRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly ReleaseService $releaseService,
    ) {}

    public function store(AddReleasePullRequestRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $this->releaseService->addPullRequest(
            $org,
            $record,
            $request->user()->id,
            (int) $request->validated('pull_request_id'),
        );

        return $this->successResponse(
            data: $this->releases->pullRequestsForRelease($record->id)
                ->map(fn (object $pr): array => [
                    'id' => (int) $pr->id,
                    'number' => (int) $pr->number,
                    'title' => $pr->title,
                ])->all(),
            status: 201,
        );
    }

    public function destroy(RemoveReleasePullRequestRequest $request, int $org, int $release, int $pullRequest): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $this->releaseService->removePullRequest($record, $request->user()->id, $pullRequest);

        return $this->successResponse(data: ['status' => 'removed']);
    }
}
