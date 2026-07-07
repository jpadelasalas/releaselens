<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Shared\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    use ApiResponse;

    public function __construct(
        private readonly FeatureFlags $features
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->features->enabled($feature)) {
            return $this->errorResponse(
                code: 'FEATURE_DISABLED',
                message: 'This feature is not enabled.',
                status: 404,
            );
        }

        return $next($request);
    }
}
