<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_register_with_a_normalized_unique_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '  Alex Rivera  ',
            'email' => '  Alex@Example.COM ',
            'password' => 'release-lens-2026',
            'password_confirmation' => 'release-lens-2026',
            'timezone' => 'Asia/Manila',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Alex Rivera')
            ->assertJsonPath('data.user.email', 'alex@example.com')
            ->assertJsonPath('data.user.timezone', 'Asia/Manila')
            ->assertJsonPath('data.memberships', [])
            ->assertJsonPath('data.active_organization_id', null);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'alex@example.com',
            'normalized_email' => 'alex@example.com',
        ]);
    }

    public function test_registration_rejects_case_insensitive_duplicate_email(): void
    {
        $this->user('alex@example.com');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Alex',
            'email' => 'ALEX@EXAMPLE.COM',
            'password' => 'release-lens-2026',
            'password_confirmation' => 'release-lens-2026',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['email']]]);
    }

    public function test_registered_user_can_login_and_read_memberships(): void
    {
        $user = $this->user('alex@example.com');
        $organizationId = $this->organization('Release Engineering', 'release-engineering');
        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $login = $this
            ->withSession([
                'releaselens.context' => [
                    'type' => 'demo',
                    'organization_id' => 999,
                ],
            ])
            ->postJson('/api/v1/auth/login', [
                'email' => ' ALEX@EXAMPLE.COM ',
                'password' => 'release-lens-2026',
            ]);

        $login
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.memberships.0.organization.id', $organizationId)
            ->assertJsonPath('data.memberships.0.role', 'owner');
        $login->assertSessionMissing('releaselens.context');
        $this->assertAuthenticatedAs($user);

        $this
            ->withSession(['releaselens.active_organization_id' => $organizationId])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.active_organization_id', $organizationId);
    }

    public function test_invalid_or_disabled_user_cannot_login(): void
    {
        $user = $this->user('alex@example.com');
        $user->forceFill(['disabled_at' => now()])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alex@example.com',
            'password' => 'release-lens-2026',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->user('alex@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertGuest();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authentication_routes_are_rate_limited_and_csrf_protected(): void
    {
        $loginRoute = Route::getRoutes()->getByName('auth.login');

        $this->assertNotNull($loginRoute);
        $this->assertContains(
            'throttle:authentication',
            $loginRoute->gatherMiddleware(),
        );
        $this->assertContains(
            'web',
            $loginRoute->gatherMiddleware(),
        );

        $this->get('/api/v1/auth/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Alex Rivera',
            'email' => $email,
            'normalized_email' => mb_strtolower($email),
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }

    private function organization(string $name, string $slug): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
