<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    use ApiResponse;

    public function __invoke(
        RegisterRequest $request,
        AuthenticationService $authentication,
    ): JsonResponse {
        $user = $authentication->register($request->validated(), $request);

        return $this->successResponse(
            $authentication->sessionPayload($user, $request),
            201,
        );
    }
}
