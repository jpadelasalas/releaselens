<?php

namespace App\Modules\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Http\Requests\ListWebhookDeliveriesRequest;
use App\Modules\Webhooks\Http\Requests\ReplayWebhookDeliveryRequest;
use App\Modules\Webhooks\Http\Requests\ShowWebhookDeliveryRequest;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use Illuminate\Http\JsonResponse;

class WebhookDeliveryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WebhookDeliveryRepositoryInterface $deliveries
    ) {}

    public function index(ListWebhookDeliveriesRequest $request, int $org): JsonResponse
    {
        $paginator = $this->deliveries->paginateForOrganization(
            $org,
            $request->filters(),
            $request->perPage(),
        );

        return $this->successResponse(
            data: collect($paginator->items())->map(
                fn (object $delivery): array => $this->present($delivery)
            )->all(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function show(ShowWebhookDeliveryRequest $request, int $org, int $delivery): JsonResponse
    {
        $record = $this->deliveries->findForOrganization($org, $delivery);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Webhook delivery not found.', 404);
        }

        $attempts = $this->deliveries->attemptsForDelivery($record->id)
            ->map(fn (object $attempt): array => [
                'attempt_number' => (int) $attempt->attempt_number,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,
                'next_retry_at' => $attempt->next_retry_at,
                'error_category' => $attempt->error_category,
                'error_summary' => $attempt->error_summary,
            ])
            ->all();

        return $this->successResponse(data: [...$this->present($record), 'attempts' => $attempts]);
    }

    public function replay(ReplayWebhookDeliveryRequest $request, int $org, int $delivery): JsonResponse
    {
        $record = $this->deliveries->findForOrganization($org, $delivery);

        if ($record === null) {
            return $this->errorResponse('RESOURCE_NOT_FOUND', 'Webhook delivery not found.', 404);
        }

        if (! in_array($record->status, ['retryable_failed', 'dead_lettered'], true)) {
            return $this->errorResponse(
                code: 'WEBHOOK_REPLAY_NOT_ALLOWED',
                message: 'Only retryable_failed or dead_lettered deliveries can be replayed.',
                status: 422,
            );
        }

        $payload = is_string($record->payload) ? (json_decode($record->payload, true) ?? []) : [];
        $this->deliveries->updateStatus($record->id, WebhookDeliveryStatus::Queued, ['queued_at' => now()]);
        ProcessWebhookDeliveryJob::dispatch($record->id, $payload);

        return $this->successResponse(data: ['status' => 'queued']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $delivery): array
    {
        return [
            'id' => (int) $delivery->id,
            'github_delivery_id' => $delivery->github_delivery_id,
            'event_name' => $delivery->event_name,
            'action_name' => $delivery->action_name,
            'status' => $delivery->status,
            'repository_id' => $delivery->repository_id !== null ? (int) $delivery->repository_id : null,
            'error_category' => $delivery->error_category,
            'error_summary' => $delivery->error_summary,
            'received_at' => $delivery->received_at,
            'queued_at' => $delivery->queued_at,
            'processed_at' => $delivery->processed_at,
        ];
    }
}
