<?php

namespace App\Modules\Shared\Http\Responses;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function successResponse(
        mixed $data = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function errorResponse(
        string $code,
        string $message,
        int $status,
        array $details = []
    ): JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status);
    }
}
