<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepositoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_context_lists_only_active_organization_repositories(): void
    {
        $demoOrganizationId = $this->organization('Northstar', 'northstar');
        $otherOrganizationId = $this->organization('Private', 'private');

        $this->repository($demoOrganizationId, 'customer-portal');
        $this->repository($demoOrganizationId, 'billing-api');
        $this->repository($otherOrganizationId, 'private-api');

        $response = $this
            ->withSession([
                'releaselens.context' => [
                    'type' => 'demo',
                    'session_id' => 'demo-session-id',
                    'organization_id' => $demoOrganizationId,
                    'organization_slug' => 'northstar',
                ],
            ])
            ->getJson("/api/v1/organizations/{$demoOrganizationId}/repositories");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'billing-api')
            ->assertJsonMissing(['name' => 'private-api']);
    }

    private function organization(string $name, string $slug): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'timezone' => 'UTC',
            'is_demo' => $slug === 'northstar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(int $organizationId, string $name): void
    {
        DB::table('repositories')->insert([
            'organization_id' => $organizationId,
            'github_repository_id' => random_int(100_000_000, 999_999_999),
            'name' => $name,
            'full_name' => "northstar/{$name}",
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
