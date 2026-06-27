<?php

namespace App\Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Http\Requests\AddOrganizationMemberRequest;
use App\Modules\Organizations\Services\OrganizationWorkspaceService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AddOrganizationMemberController extends Controller
{
    use ApiResponse;

    public function __invoke(
        AddOrganizationMemberRequest $request,
        int $org,
        OrganizationWorkspaceService $workspaces,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $workspaces->addMember(
                $user,
                $org,
                $request->string('email')->toString(),
                OrganizationRole::from($request->string('role')->toString()),
                $request,
            ),
            201,
        );
    }
}
