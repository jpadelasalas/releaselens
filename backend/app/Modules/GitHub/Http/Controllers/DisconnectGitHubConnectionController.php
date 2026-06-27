<?php

namespace App\Modules\GitHub\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\GitHub\Services\GitHubConnectionService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisconnectGitHubConnectionController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $org, GitHubConnectionService $connections): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $connections->disconnect($user, $org, $request);

        return $this->successResponse(['disconnected' => true]);
    }
}
