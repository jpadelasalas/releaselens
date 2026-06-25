<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoSessionIsReadOnly
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $this->isDemoContext($request) &&
            ! $request->isMethodSafe() &&
            $request->route()?->getName() !== 'demo.session'
        ) {
            return $this->readOnlyResponse();
        }

        return $next($request);
    }

    private function isDemoContext(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $context = $request->session()->get('releaselens.context');

        return is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            isset($context['organization_id']);
    }

    private function readOnlyResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'DEMO_READ_ONLY',
                'message' => 'The demo workspace is read-only.',
            ],
        ], 403);
    }
}
