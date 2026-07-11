<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseReadinessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.releases', true);
    }

    public function test_show_reports_no_pull_requests_warning_for_an_empty_release(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}",
        );

        $response->assertOk();
        $this->assertContains('no_pull_requests', array_column($response->json('data.readiness_warnings'), 'code'));
    }

    public function test_export_markdown_returns_a_markdown_document(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        $response = $this->actingAs($owner)->get(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/export.md",
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('# July release', $response->getContent());
    }

    public function test_export_markdown_is_scoped_to_the_organization(): void
    {
        $organizationId = $this->organization();
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        $outsider = $this->member($this->organization(), 'owner');

        $response = $this->actingAs($outsider)->get(
            "/api/v1/organizations/{$organizationId}/releases/{$release->id}/export.md",
        );

        $response->assertForbidden();
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
