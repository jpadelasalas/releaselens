<?php

namespace App\Modules\Operations\Services;

use App\Modules\Operations\Contracts\HealthCheckInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseHealthCheck implements HealthCheckInterface
{
    public function readiness(): array
    {
        try {
            DB::select('select 1');

            return [
                'healthy' => true,
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                ],
            ];
        } catch (Throwable) {
            return [
                'healthy' => false,
                'checks' => [
                    'application' => 'ok',
                    'database' => 'unavailable',
                ],
            ];
        }
    }
}
