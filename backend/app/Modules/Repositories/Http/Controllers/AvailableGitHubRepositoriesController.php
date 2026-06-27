<?php

namespace App\Modules\Repositories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Repositories\Services\OrganizationRepositoryService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableGitHubRepositoriesController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        int $org,
        OrganizationRepositoryService $repositories,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $repositories->available($user, $org),
        );
    }
}
