<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the required GitHub webhook request handling order: size and
 * content-type checks, raw-body preservation, header validation, then a
 * timing-safe HMAC-SHA256 signature check. JSON is only decoded after the
 * signature is confirmed valid, so an unauthenticated caller can never
 * reach domain code (docs/v2 blueprint section 13.1).
 */
class VerifyGitHubWebhookSignature
{
    use ApiResponse;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return $this->rejectHeaders('Expected a JSON payload.');
        }

        $rawBody = $request->getContent();

        if ($rawBody === '' || strlen($rawBody) > $this->maxPayloadBytes()) {
            return $this->rejectHeaders('The webhook payload is missing or exceeds the allowed size.');
        }

        $deliveryId = $request->header('X-GitHub-Delivery');
        $eventName = $request->header('X-GitHub-Event');
        $signatureHeader = $request->header('X-Hub-Signature-256');

        if (! is_string($deliveryId) || $deliveryId === '' || ! is_string($eventName) || $eventName === '') {
            return $this->rejectHeaders('Required GitHub webhook headers are missing.');
        }

        if (! $this->hasValidSignature($rawBody, $signatureHeader)) {
            return $this->errorResponse(
                code: 'WEBHOOK_SIGNATURE_INVALID',
                message: 'The webhook signature is missing or invalid.',
                status: 401,
            );
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return $this->rejectHeaders('The webhook payload is not valid JSON.');
        }

        $request->attributes->set('webhook_payload', $payload);
        $request->attributes->set('webhook_payload_sha256', hash('sha256', $rawBody));

        return $next($request);
    }

    private function hasValidSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) config('releaselens.github.webhook_secret');

        if ($secret === '' || ! is_string($signatureHeader) || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    private function maxPayloadBytes(): int
    {
        return (int) config('releaselens.github.webhook_max_payload_bytes', 5_242_880);
    }

    private function rejectHeaders(string $message): Response
    {
        return $this->errorResponse(
            code: 'WEBHOOK_HEADERS_INVALID',
            message: $message,
            status: 400,
        );
    }
}
