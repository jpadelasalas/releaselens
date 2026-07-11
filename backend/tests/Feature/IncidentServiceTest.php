<?php

namespace Tests\Feature;

use App\Modules\Incidents\Enums\IncidentSeverity;
use App\Modules\Incidents\Enums\IncidentState;
use App\Modules\Incidents\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_records_a_timeline_entry(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();

        $incident = app(IncidentService::class)->create($organizationId, $userId, [
            'title' => 'API latency spike',
            'severity' => 'sev2',
        ]);

        $this->assertSame('investigating', $incident->state);
        $this->assertSame(
            1,
            DB::table('incident_timeline_entries')->where('incident_id', $incident->id)->count(),
        );
    }

    public function test_transition_moves_through_the_allowed_states(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Sev3 blip', 'severity' => 'sev3']);

        $incident = $service->transition($incident, $userId, IncidentState::Identified);
        $incident = $service->transition($incident, $userId, IncidentState::Monitoring);
        $incident = $service->transition($incident, $userId, IncidentState::Resolved);

        $this->assertSame('resolved', $incident->state);
        $this->assertNotNull($incident->resolved_at);

        $incident = $service->transition($incident, $userId, IncidentState::Closed);
        $this->assertSame('closed', $incident->state);
    }

    public function test_transition_rejects_a_disallowed_jump(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Sev4', 'severity' => 'sev4']);

        $this->expectExceptionMessage('Cannot transition an incident from investigating to closed.');

        $service->transition($incident, $userId, IncidentState::Closed);
    }

    public function test_a_sev1_incident_cannot_close_without_a_published_postmortem(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Outage', 'severity' => 'sev1']);
        $incident = $service->transition($incident, $userId, IncidentState::Identified);
        $incident = $service->transition($incident, $userId, IncidentState::Monitoring);
        $incident = $service->transition($incident, $userId, IncidentState::Resolved);

        $this->expectExceptionMessage('A published postmortem is required before a sev1 incident can be closed.');

        $service->transition($incident, $userId, IncidentState::Closed);
    }

    public function test_a_sev1_incident_can_close_once_the_postmortem_is_published(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Outage', 'severity' => 'sev1']);
        $incident = $service->transition($incident, $userId, IncidentState::Identified);
        $incident = $service->transition($incident, $userId, IncidentState::Monitoring);
        $incident = $service->transition($incident, $userId, IncidentState::Resolved);

        $service->savePostmortem($incident, $userId, ['summary' => 'Root cause and remediation.']);
        $service->publishPostmortem($incident, $userId);

        $incident = $service->transition($incident, $userId, IncidentState::Closed);

        $this->assertSame('closed', $incident->state);
    }

    public function test_a_sev4_incident_can_close_without_a_postmortem(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Minor blip', 'severity' => 'sev4']);
        $incident = $service->transition($incident, $userId, IncidentState::Identified);
        $incident = $service->transition($incident, $userId, IncidentState::Monitoring);
        $incident = $service->transition($incident, $userId, IncidentState::Resolved);
        $incident = $service->transition($incident, $userId, IncidentState::Closed);

        $this->assertSame('closed', $incident->state);
    }

    public function test_action_items_can_be_added_and_completed(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Sev3', 'severity' => 'sev3']);

        $item = $service->addActionItem($incident, $userId, 'Add alerting for queue depth', null);
        $completed = $service->completeActionItem($incident, $userId, $item->id);

        $this->assertSame(1, (int) $completed->is_completed);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_incidents_can_be_linked_to_other_entities(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(IncidentService::class);
        $incident = $service->create($organizationId, $userId, ['title' => 'Sev3', 'severity' => 'sev3']);

        $link = $service->linkEntity($incident, $userId, 'deployment', 42);

        $this->assertSame('deployment', $link->linkable_type);
        $this->assertSame(42, $link->linkable_id);
    }

    public function test_requires_postmortem_is_true_only_for_sev1_and_sev2(): void
    {
        $this->assertTrue(IncidentSeverity::Sev1->requiresPostmortem());
        $this->assertTrue(IncidentSeverity::Sev2->requiresPostmortem());
        $this->assertFalse(IncidentSeverity::Sev3->requiresPostmortem());
        $this->assertFalse(IncidentSeverity::Sev4->requiresPostmortem());
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

    private function user(): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'user-'.uniqid('', true).'@example.com',
            'normalized_email' => 'user-'.uniqid('', true).'@example.com',
            'password' => bcrypt('release-lens-2026'),
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
