<?php

namespace App\Modules\Operations\Contracts;

interface HealthCheckInterface
{
    /**
     * @return array{
     *     healthy: bool,
     *     checks: array{application: string, database: string}
     * }
     */
    public function readiness(): array;
}
