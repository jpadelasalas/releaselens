<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListOrganizationsController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        OrganizationWorkspaceService $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse($workspaces->listForUser($user));
    }
}
