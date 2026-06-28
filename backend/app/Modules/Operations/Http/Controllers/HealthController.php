<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Contracts\HealthCheckInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(HealthCheckInterface $health): JsonResponse
    {
        $readiness = $health->readiness();

        if (! $readiness['healthy']) {
            Log::warning('Application readiness check failed', [
                'failed_check' => 'database',
            ]);
        }

        return response()->json([
            'status' => $readiness['healthy'] ? 'ok' : 'unavailable',
            'checks' => $readiness['checks'],
            'checked_at' => now('UTC')->toIso8601String(),
        ], $readiness['healthy'] ? 200 : 503);
    }
}
