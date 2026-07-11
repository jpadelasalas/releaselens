<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Incidents\Contracts\IncidentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncidentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.incidents', true);
    }

    public function test_an_owner_can_create_an_incident(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents",
            ['title' => 'API latency spike', 'severity' => 'sev2'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.state', 'investigating');
    }

    public function test_a_viewer_cannot_create_an_incident(): void
    {
        $organizationId = $this->organization();
        $viewer = $this->member($organizationId, 'viewer');

        $response = $this->actingAs($viewer)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents",
            ['title' => 'API latency spike', 'severity' => 'sev2'],
        );

        $response->assertForbidden();
    }

    public function test_list_filters_by_severity(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incidents = app(IncidentRepositoryInterface::class);
        $incidents->create($organizationId, ['title' => 'Minor', 'severity' => 'sev4']);
        $incidents->create($organizationId, ['title' => 'Major', 'severity' => 'sev1']);

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/incidents?severity=sev1",
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.title', 'Major');
    }

    public function test_show_includes_timeline_action_items_links_and_postmortem(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incident = app(IncidentRepositoryInterface::class)->create($organizationId, ['title' => 'Sev3', 'severity' => 'sev3']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/action-items",
            ['description' => 'Add alerting'],
        )->assertCreated();
        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/links",
            ['linkable_type' => 'deployment', 'linkable_id' => 5],
        )->assertCreated();

        $response = $this->actingAs($owner)->getJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}",
        );

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data.timeline')));
        $this->assertCount(1, $response->json('data.action_items'));
        $this->assertCount(1, $response->json('data.links'));
        $this->assertNull($response->json('data.postmortem'));
    }

    public function test_transition_through_the_lifecycle(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incident = app(IncidentRepositoryInterface::class)->create($organizationId, ['title' => 'Sev3', 'severity' => 'sev3']);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/transition",
            ['to' => 'identified'],
        )->assertOk()->assertJsonPath('data.state', 'identified');
    }

    public function test_transition_rejects_a_disallowed_jump(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incident = app(IncidentRepositoryInterface::class)->create($organizationId, ['title' => 'Sev3', 'severity' => 'sev3']);

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/transition",
            ['to' => 'closed'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INCIDENT_INVALID_TRANSITION');
    }

    public function test_a_sev1_incident_cannot_close_without_a_published_postmortem_via_the_api(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incidents = app(IncidentRepositoryInterface::class);
        $incident = $incidents->create($organizationId, ['title' => 'Outage', 'severity' => 'sev1']);
        $incidents->updateState($incident->id, 'resolved');

        $response = $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/transition",
            ['to' => 'closed'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INCIDENT_POSTMORTEM_REQUIRED');
    }

    public function test_saving_and_publishing_a_postmortem_unblocks_closing_a_sev1_incident(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $incidents = app(IncidentRepositoryInterface::class);
        $incident = $incidents->create($organizationId, ['title' => 'Outage', 'severity' => 'sev1']);
        $incidents->updateState($incident->id, 'resolved');

        $this->actingAs($owner)->putJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/postmortem",
            ['summary' => 'Root cause and remediation.'],
        )->assertOk()->assertJsonPath('data.is_published', false);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/postmortem/publish",
        )->assertOk()->assertJsonPath('data.is_published', true);

        $this->actingAs($owner)->postJson(
            "/api/v1/organizations/{$organizationId}/incidents/{$incident->id}/transition",
            ['to' => 'closed'],
        )->assertOk()->assertJsonPath('data.state', 'closed');
    }

    public function test_a_non_member_cannot_view_another_organizations_incidents(): void
    {
        $organizationId = $this->organization();
        $outsider = $this->member($this->organization(), 'owner');

        $response = $this->actingAs($outsider)->getJson(
            "/api/v1/organizations/{$organizationId}/incidents",
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
