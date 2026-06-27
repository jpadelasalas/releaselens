<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivateOrganizationController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        int $org,
        OrganizationWorkspaceService $workspaces,
        AuthenticationService $authentication,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspaces->activate($user, $org, $request);

        return $this->successResponse(
            $authentication->sessionPayload($user, $request),
        );
    }
}
