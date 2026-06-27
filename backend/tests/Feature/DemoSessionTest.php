<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDemoSessionIsReadOnly;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_session_can_be_created_without_account_or_github_authorization(): void
    {
        $organizationId = $this->seedDemoOrganization();

        $response = $this->postJson('/api/v1/demo/session');

        $response
            ->assertOk()
            ->assertJsonPath('data.session.type', 'demo')
            ->assertJsonPath('data.session.read_only', true)
            ->assertJsonPath('data.organization.id', $organizationId)
            ->assertJsonPath('data.organization.slug', 'northstar-engineering')
            ->assertJsonPath('data.organization.is_demo', true)
            ->assertJsonPath('data.capabilities.can_connect_github', false)
            ->assertJsonPath('data.capabilities.can_mutate_demo', false);

        $this->assertGuest();
        $response->assertSessionHas('releaselens.context.type', 'demo');
        $response->assertSessionHas('releaselens.context.organization_id', $organizationId);
    }

    public function test_demo_session_is_idempotent_in_the_same_browser_session(): void
    {
        $this->seedDemoOrganization();

        $first = $this->postJson('/api/v1/demo/session');
        $second = $this->postJson('/api/v1/demo/session');

        $first->assertOk();
        $second->assertOk();

        $this->assertSame(
            $first->json('data.session.id'),
            $second->json('data.session.id')
        );
    }

    public function test_request_cannot_select_a_different_organization(): void
    {
        $demoOrganizationId = $this->seedDemoOrganization();
        $otherOrganizationId = $this->seedOrganization(
            name: 'Private Engineering',
            slug: 'private-engineering',
            isDemo: false
        );

        $response = $this->postJson('/api/v1/demo/session', [
            'organization_id' => $otherOrganizationId,
            'slug' => 'private-engineering',
            'is_demo' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.organization.id', $demoOrganizationId)
            ->assertJsonPath('data.organization.slug', 'northstar-engineering')
            ->assertJsonPath('data.organization.is_demo', true);

        $response->assertSessionHas('releaselens.context.organization_id', $demoOrganizationId);
    }

    public function test_state_changing_demo_requests_are_rejected_centrally(): void
    {
        $this->registerDemoWriteProbeRoute();

        $response = $this
            ->withSession([
                'releaselens.context' => [
                    'type' => 'demo',
                    'session_id' => 'demo-session-id',
                    'organization_id' => 1,
                    'organization_slug' => 'northstar-engineering',
                ],
            ])
            ->postJson('/api/v1/demo/write-probe');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DEMO_READ_ONLY');
    }

    public function test_demo_session_route_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('demo.session');

        $this->assertNotNull($route);
        $this->assertContains('throttle:demo-session', $route->gatherMiddleware());
    }

    public function test_demo_session_response_does_not_expose_sensitive_data(): void
    {
        $this->seedDemoOrganization();

        $response = $this->postJson('/api/v1/demo/session');

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringNotContainsString('token', $content);
        $this->assertStringNotContainsString('email', $content);
        $this->assertStringNotContainsString('secret', $content);
        $this->assertStringNotContainsString('private', $content);
        $this->assertStringNotContainsString('github_installation', $content);
    }

    public function test_missing_demo_seed_returns_a_controlled_error(): void
    {
        $response = $this->postJson('/api/v1/demo/session');

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('error.code', 'DEMO_NOT_READY')
            ->assertJsonPath(
                'error.message',
                'The demo workspace has not been seeded yet.'
            );

        $response->assertSessionMissing('releaselens.context');
    }

    private function registerDemoWriteProbeRoute(): void
    {
        Route::post('/api/v1/demo/write-probe', fn () => response()->json(['ok' => true]))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SubstituteBindings::class,
                EnsureDemoSessionIsReadOnly::class,
            ])
            ->name('demo.write-probe');
    }

    private function seedDemoOrganization(): int
    {
        return $this->seedOrganization(
            name: 'Northstar Engineering',
            slug: 'northstar-engineering',
            isDemo: true
        );
    }

    private function seedOrganization(string $name, string $slug, bool $isDemo): int
    {
        $now = Carbon::parse('2026-06-19T12:00:00Z');

        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'Asia/Manila',
            'is_demo' => $isDemo,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
