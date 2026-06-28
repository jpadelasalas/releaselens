<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestCorrelationId
{
    public const HEADER = 'X-Request-ID';

    public const ATTRIBUTE = 'correlation_id';

    public const STARTED_AT_ATTRIBUTE = 'correlation_started_at';

    public const FINALIZED_ATTRIBUTE = 'correlation_finalized';

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $correlationId = $this->correlationId($request);

        $request->attributes->set(self::ATTRIBUTE, $correlationId);
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, $startedAt);
        Log::withContext([
            'correlation_id' => $correlationId,
            'organization_id' => null,
        ]);

        return $this->finalize($request, $next($request));
    }

    public function finalize(Request $request, Response $response): Response
    {
        if ($request->attributes->getBoolean(self::FINALIZED_ATTRIBUTE)) {
            return $response;
        }

        $correlationId = $request->attributes->get(self::ATTRIBUTE);

        if (! is_string($correlationId)) {
            return $response;
        }

        $request->attributes->set(self::FINALIZED_ATTRIBUTE, true);

        $organizationId = $this->organizationId($request);
        $startedAt = $request->attributes->get(self::STARTED_AT_ATTRIBUTE);

        if ($organizationId !== null) {
            Log::withContext([
                'correlation_id' => $correlationId,
                'organization_id' => $organizationId,
            ]);
        }

        $response->headers->set(self::HEADER, $correlationId);

        Log::info('HTTP request completed', [
            'correlation_id' => $correlationId,
            'organization_id' => $organizationId,
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => is_int($startedAt)
                ? round((hrtime(true) - $startedAt) / 1_000_000, 2)
                : null,
        ]);

        return $response;
    }

    private function correlationId(Request $request): string
    {
        $candidate = trim((string) $request->header(self::HEADER));

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{7,127}\z/D', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::ulid();
    }

    private function organizationId(Request $request): ?int
    {
        $organizationId = $request->route('org');

        return is_numeric($organizationId) && (int) $organizationId > 0
            ? (int) $organizationId
            : null;
    }
}
