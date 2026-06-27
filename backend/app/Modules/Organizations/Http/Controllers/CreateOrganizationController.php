<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Organizations\Http\Requests\CreateOrganizationRequest;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreateOrganizationController extends Controller
{
    use ApiResponse;

    public function __invoke(
        CreateOrganizationRequest $request,
        OrganizationWorkspaceService $workspaces,
        AuthenticationService $authentication,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspaces->create($user, $request->validated(), $request);

        return $this->successResponse(
            $authentication->sessionPayload($user, $request),
            201,
        );
    }
}
