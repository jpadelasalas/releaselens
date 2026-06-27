<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Http\Requests\UpdateOrganizationMemberRequest;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateOrganizationMemberController extends Controller
{
    use ApiResponse;

    public function __invoke(
        UpdateOrganizationMemberRequest $request,
        int $org,
        int $member,
        OrganizationWorkspaceService $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $workspaces->changeMemberRole(
                $user,
                $org,
                $member,
                OrganizationRole::from($request->string('role')->toString()),
                $request,
            ),
        );
    }
}
