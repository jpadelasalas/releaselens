<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Operations\Contracts\HealthCheckInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_reports_application_and_database_health_without_sensitive_details(): void
    {
        Log::spy();

        $response = $this
            ->withHeader('X-Request-ID', 'release-test-1234')
            ->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID', 'release-test-1234')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.application', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['checked_at']);

        $this->assertStringNotContainsString(
            'password',
            mb_strtolower($response->getContent()),
        );
        $this->assertStringNotContainsString(
            'connection',
            mb_strtolower($response->getContent()),
        );

        Log::shouldHaveReceived('info')
            ->once()
            ->with(
                'HTTP request completed',
                Mockery::on(fn (array $context): bool => $context['method'] === 'GET' &&
                    $context['route'] === 'health.readiness' &&
                    $context['status'] === 200 &&
                    $context['duration_ms'] >= 0),
            );
        Log::shouldHaveReceived('withContext')
            ->with([
                'correlation_id' => 'release-test-1234',
                'organization_id' => null,
            ])
            ->once();
    }

    public function test_readiness_returns_safe_service_unavailable_response_when_database_is_down(): void
    {
        $health = Mockery::mock(HealthCheckInterface::class);
        $health->shouldReceive('readiness')->once()->andReturn([
            'healthy' => false,
            'checks' => [
                'application' => 'ok',
                'database' => 'unavailable',
            ],
        ]);
        $this->app->instance(HealthCheckInterface::class, $health);

        $this->getJson('/api/v1/health')
            ->assertServiceUnavailable()
            ->assertJson([
                'status' => 'unavailable',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'unavailable',
                ],
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('message');
    }

    public function test_invalid_request_identifier_is_replaced_and_errors_keep_the_identifier(): void
    {
        $generated = $this
            ->withHeader('X-Request-ID', 'short')
            ->getJson('/api/v1/health')
            ->headers->get('X-Request-ID');

        $this->assertIsString($generated);
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $generated);

        Log::spy();

        $this
            ->withHeader('X-Request-ID', 'release-error-1234')
            ->getJson('/api/v1/not-a-route')
            ->assertNotFound()
            ->assertHeader('X-Request-ID', 'release-error-1234');

        Log::shouldHaveReceived('info')
            ->with(
                'HTTP request completed',
                Mockery::on(fn (array $context): bool => $context['correlation_id'] === 'release-error-1234' &&
                    $context['route'] === 'api/v1/not-a-route' &&
                    $context['status'] === 404 &&
                    $context['duration_ms'] >= 0),
            )
            ->once();
    }

    public function test_numeric_organization_route_is_added_to_safe_log_context(): void
    {
        $user = User::factory()->create();
        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Operations Team',
            'slug' => 'operations-team',
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'viewer',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Log::spy();

        $this
            ->actingAs($user)
            ->withHeader('X-Request-ID', 'release-org-1234')
            ->getJson("/api/v1/organizations/{$organizationId}/repositories")
            ->assertOk()
            ->assertHeader('X-Request-ID', 'release-org-1234');

        Log::shouldHaveReceived('withContext')
            ->with([
                'correlation_id' => 'release-org-1234',
                'organization_id' => $organizationId,
            ])
            ->once();
    }
}
