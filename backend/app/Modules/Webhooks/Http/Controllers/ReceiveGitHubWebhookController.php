<?php

namespace App\Modules\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use App\Modules\Shared\Http\Responses\ApiResponse;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Enums\WebhookDeliveryStatus;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiveGitHubWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WebhookDeliveryRepositoryInterface $deliveries,
        private readonly SynchronizationRepositoryInterface $synchronization,
        private readonly GitHubConnectionRepositoryInterface $connections,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $deliveryId = (string) $request->header('X-GitHub-Delivery');

        if ($this->deliveries->findByGitHubDeliveryId($deliveryId) !== null) {
            return $this->successResponse(data: ['status' => 'duplicate'], status: 202);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->attributes->get('webhook_payload', []);
        $githubInstallationId = $this->intOrNull($payload['installation']['id'] ?? null);
        $repository = $this->intOrNull($payload['repository']['id'] ?? null) !== null
            ? $this->synchronization->repositoryByGitHubId((int) $payload['repository']['id'])
            : null;
        $organizationId = $repository->organization_id ?? null;

        if ($organizationId === null && $githubInstallationId !== null) {
            $installation = $this->connections->activeByInstallationId($githubInstallationId);
            $organizationId = $installation->organization_id ?? null;
        }

        $delivery = $this->deliveries->create([
            'organization_id' => $organizationId,
            'repository_id' => $repository->id ?? null,
            'github_delivery_id' => $deliveryId,
            'event_name' => (string) $request->header('X-GitHub-Event'),
            'action_name' => is_string($payload['action'] ?? null) ? $payload['action'] : null,
            'github_hook_id' => $this->intOrNull($payload['hook_id'] ?? null),
            'github_installation_id' => $githubInstallationId,
            'payload' => $payload,
            'payload_sha256' => (string) $request->attributes->get('webhook_payload_sha256'),
        ]);

        $this->deliveries->updateStatus($delivery->id, WebhookDeliveryStatus::Queued, [
            'queued_at' => now(),
        ]);

        ProcessWebhookDeliveryJob::dispatch($delivery->id, $payload);

        return $this->successResponse(data: ['status' => 'accepted'], status: 202);
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
