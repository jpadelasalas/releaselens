<?php

namespace App\Modules\Repositories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Repositories\Http\Requests\UpdateRepositoryMonitoringRequest;
use App\Modules\Repositories\Services\OrganizationRepositoryService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateRepositoryMonitoringController extends Controller
{
    use ApiResponse;

    public function __invoke(
        UpdateRepositoryMonitoringRequest $request,
        int $org,
        int $repository,
        OrganizationRepositoryService $repositories,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $repositories->changeMonitoring(
                $user,
                $org,
                $repository,
                $request->boolean('sync_enabled'),
            ),
        );
    }
}
