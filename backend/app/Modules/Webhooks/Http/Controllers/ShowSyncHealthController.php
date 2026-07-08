<?php

namespace App\Modules\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Webhooks\Http\Requests\ShowSyncHealthRequest;
use App\Modules\Webhooks\Services\SyncHealthService;
use Illuminate\Http\JsonResponse;

class ShowSyncHealthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SyncHealthService $syncHealth
    ) {}

    public function __invoke(ShowSyncHealthRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(data: $this->syncHealth->summarize($org));
    }
}
