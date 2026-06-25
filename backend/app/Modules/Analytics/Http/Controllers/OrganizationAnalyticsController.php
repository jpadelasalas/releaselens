<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Http\Requests\AnalyticsRequest;
use App\Modules\Analytics\Services\OrganizationAnalyticsService;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizationAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrganizationAnalyticsService $analytics
    ) {}

    public function summary(AnalyticsRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->summary($org, $request->filters()),
        );
    }

    public function trends(AnalyticsRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->trends($org, $request->filters()),
        );
    }

    public function distributions(AnalyticsRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->distributions($org, $request->filters()),
        );
    }

    public function attention(AnalyticsRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(
            $this->analytics->attention($org, $request->filters()),
        );
    }
}
