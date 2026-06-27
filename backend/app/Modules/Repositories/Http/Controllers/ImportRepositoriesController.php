<?php

namespace App\Modules\Repositories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Repositories\Http\Requests\ImportRepositoriesRequest;
use App\Modules\Repositories\Services\OrganizationRepositoryService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ImportRepositoriesController extends Controller
{
    use ApiResponse;

    public function __invoke(
        ImportRepositoriesRequest $request,
        int $org,
        OrganizationRepositoryService $repositories,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $repositories->import(
                $user,
                $org,
                array_map('intval', $request->validated('repository_ids')),
            ),
        );
    }
}
