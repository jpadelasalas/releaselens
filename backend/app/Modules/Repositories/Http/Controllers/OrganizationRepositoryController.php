<?php

namespace App\Modules\Repositories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Repositories\Http\Requests\ListRepositoriesRequest;
use App\Modules\Repositories\Services\OrganizationRepositoryService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationRepositoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrganizationRepositoryService $repositories
    ) {}

    public function index(
        ListRepositoriesRequest $request,
        int $org
    ): JsonResponse {
        return $this->successResponse($this->repositories->list($org));
    }
}
