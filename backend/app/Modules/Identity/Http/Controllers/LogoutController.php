<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Request $request,
        AuthenticationService $authentication,
    ): JsonResponse {
        $authentication->logout($request);

        return $this->successResponse();
    }
}
