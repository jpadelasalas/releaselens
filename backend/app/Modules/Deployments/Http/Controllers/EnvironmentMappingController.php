<?php

namespace App\Modules\Deployments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Contracts\EnvironmentMappingRepositoryInterface;
use App\Modules\Deployments\Http\Requests\ListEnvironmentMappingsRequest;
use App\Modules\Deployments\Http\Requests\UpsertEnvironmentMappingRequest;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class EnvironmentMappingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EnvironmentMappingRepositoryInterface $mappings,
    ) {}

    public function index(ListEnvironmentMappingsRequest $request, int $org): JsonResponse
    {
        return $this->successResponse(
            data: $this->mappings->listForOrganization($org)
                ->map(fn (object $mapping): array => $this->present($mapping))->all(),
        );
    }

    public function store(UpsertEnvironmentMappingRequest $request, int $org): JsonResponse
    {
        $mapping = $this->mappings->upsertForOrganization(
            $org,
            (string) $request->validated('source_environment'),
            (string) $request->validated('normalized_environment'),
            (bool) $request->validated('is_production'),
        );

        return $this->successResponse(data: $this->present($mapping), status: 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $mapping): array
    {
        return [
            'id' => (int) $mapping->id,
            'source_environment' => $mapping->source_environment,
            'normalized_environment' => $mapping->normalized_environment,
            'is_production' => (bool) $mapping->is_production,
        ];
    }
}
