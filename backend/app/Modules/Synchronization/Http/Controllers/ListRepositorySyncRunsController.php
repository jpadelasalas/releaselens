<?php

namespace App\Modules\Synchronization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Synchronization\Services\SynchronizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListRepositorySyncRunsController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $org, int $repository, SynchronizationService $sync): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $sync->history($user, $org, $repository),
        );
    }
}
