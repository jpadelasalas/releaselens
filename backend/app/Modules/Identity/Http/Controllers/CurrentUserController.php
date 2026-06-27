<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        AuthenticationService $authentication,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            $authentication->sessionPayload($user, $request),
        );
    }
}
