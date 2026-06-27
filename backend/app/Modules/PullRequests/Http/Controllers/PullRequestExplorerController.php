<?php

namespace App\Modules\PullRequests\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PullRequests\Http\Requests\ListPullRequestsRequest;
use App\Modules\PullRequests\Services\PullRequestExplorerService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PullRequestExplorerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PullRequestExplorerService $pullRequests
    ) {}

    public function index(
        ListPullRequestsRequest $request,
        int $org
    ): JsonResponse {
        $result = $this->pullRequests->list($org, $request->filters());

        return $this->successResponse(
            data: $result['records'],
            meta: $result['meta'],
        );
    }
}
