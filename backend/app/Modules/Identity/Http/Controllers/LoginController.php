<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    use ApiResponse;

    public function __invoke(
        LoginRequest $request,
        AuthenticationService $authentication,
    ): JsonResponse {
        $user = $authentication->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        if ($user === null) {
            return $this->errorResponse(
                code: 'INVALID_CREDENTIALS',
                message: 'The provided credentials are invalid.',
                status: 422,
            );
        }

        return $this->successResponse(
            $authentication->sessionPayload($user, $request),
        );
    }
}
