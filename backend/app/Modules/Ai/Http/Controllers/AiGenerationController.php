<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Contracts\AiGenerationRepositoryInterface;
use App\Modules\Ai\Exceptions\AiRuleException;
use App\Modules\Ai\Http\Requests\CreateAiGenerationRequest;
use App\Modules\Ai\Http\Requests\ListAiGenerationsRequest;
use App\Modules\Ai\Services\AiReleaseNotesService;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AiGenerationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleaseRepositoryInterface $releases,
        private readonly AiGenerationRepositoryInterface $generations,
        private readonly AiReleaseNotesService $aiReleaseNotes,
    ) {}

    public function index(ListAiGenerationsRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        return $this->successResponse(
            data: $this->generations->forRelease($record->id)
                ->map(fn (object $generation): array => $this->present($generation))->all(),
        );
    }

    public function store(CreateAiGenerationRequest $request, int $org, int $release): JsonResponse
    {
        $record = $this->releases->findForOrganization($org, $release);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Release not found.', 404);
        }

        try {
            $generation = $this->aiReleaseNotes->generate(
                $org,
                $record,
                $this->releases->pullRequestsForRelease($record->id),
                $request->user()->id,
            );
        } catch (AiRuleException $exception) {
            return $this->errorResponse($exception->errorCode, $exception->getMessage(), $exception->status);
        }

        return $this->successResponse(data: $this->present($generation), status: 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $generation): array
    {
        return [
            'id' => (int) $generation->id,
            'provider' => $generation->provider,
            'status' => $generation->status,
            'input_fields' => json_decode($generation->input_fields, true),
            'output' => $generation->output,
            'error_message' => $generation->error_message,
            'created_at' => $generation->created_at,
        ];
    }
}
