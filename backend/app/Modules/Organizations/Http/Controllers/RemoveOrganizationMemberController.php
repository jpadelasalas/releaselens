<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoveOrganizationMemberController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        int $org,
        int $member,
        OrganizationWorkspaceService $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspaces->removeMember($user, $org, $member, $request);

        return $this->successResponse();
    }
}
