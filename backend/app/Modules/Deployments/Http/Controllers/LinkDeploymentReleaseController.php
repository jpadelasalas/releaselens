<?php

namespace App\Modules\Deployments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Contracts\DeploymentRepositoryInterface;
use App\Modules\Deployments\Http\Requests\LinkDeploymentReleaseRequest;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LinkDeploymentReleaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DeploymentRepositoryInterface $deployments,
        private readonly ReleaseRepositoryInterface $releases,
    ) {}

    public function __invoke(LinkDeploymentReleaseRequest $request, int $org, int $deployment): JsonResponse
    {
        $record = $this->deployments->findForOrganization($org, $deployment);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Deployment not found.', 404);
        }

        $releaseId = $request->validated('release_id');

        if ($releaseId !== null && $this->releases->findForOrganization($org, (int) $releaseId) === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        $this->deployments->linkRelease($record->id, $releaseId !== null ? (int) $releaseId : null);

        return $this->successResponse(data: ['status' => 'linked', 'release_id' => $releaseId]);
    }
}
