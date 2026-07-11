<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AiGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.ai', true);
        config()->set('releaselens.features.releases', true);
    }

    public function test_an_owner_can_generate_release_notes(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, [
            'title' => 'July release',
            'description' => 'Stability improvements.',
        ]);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        );

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'succeeded');
        $response->assertJsonPath('data.provider', 'stub');
        $this->assertStringContainsString('# July release', $response->json('data.output'));
    }

    public function test_a_viewer_cannot_generate_release_notes(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $response = $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        );

        $response->assertForbidden();
    }

    public function test_generation_history_lists_past_generations(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        )->assertCreated();

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_the_monthly_limit_is_enforced_over_the_api(): void
    {
        config()->set('releaselens.ai.monthly_generation_limit', 1);
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        )->assertCreated();

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/ai-generations",
        );

        $response->assertStatus(429);
        $response->assertJsonPath('error.code', 'AI_GENERATION_LIMIT_EXCEEDED');
    }

    private function organization(): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function member(int $organizationId, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid('', true).'@example.com',
            'normalized_email' => $role.'-'.uniqid('', true).'@example.com',
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);

        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
