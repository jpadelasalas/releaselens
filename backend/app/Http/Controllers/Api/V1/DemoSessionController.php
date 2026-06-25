<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSessionController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $demoOrganization = DB::table('organizations')
            ->select(['id', 'name', 'slug', 'timezone'])
            ->where('slug', config('releaselens.demo.organization_slug'))
            ->where('is_demo', true)
            ->first();

        if ($demoOrganization === null) {
            return $this->errorResponse(
                code: 'DEMO_NOT_READY',
                message: 'The demo workspace has not been seeded yet.',
                status: 503,
            );
        }

        $session = $request->session();
        $context = $session->get('releaselens.context');

        if (
            ! is_array($context) ||
            ($context['type'] ?? null) !== 'demo' ||
            (int) ($context['organization_id'] ?? 0) !== (int) $demoOrganization->id
        ) {
            $context = [
                'type' => 'demo',
                'session_id' => (string) Str::uuid(),
                'organization_id' => (int) $demoOrganization->id,
                'organization_slug' => $demoOrganization->slug,
                'issued_at' => Carbon::now()->toIso8601String(),
            ];

            $session->put('releaselens.context', $context);
        }

        return $this->successResponse([
            'session' => [
                'type' => 'demo',
                'id' => $context['session_id'],
                'read_only' => true,
            ],
            'organization' => [
                'id' => (int) $demoOrganization->id,
                'name' => $demoOrganization->name,
                'slug' => $demoOrganization->slug,
                'timezone' => $demoOrganization->timezone,
                'is_demo' => true,
            ],
            'capabilities' => [
                'can_read_analytics' => true,
                'can_mutate_demo' => false,
                'can_connect_github' => false,
            ],
        ]);
    }
}
