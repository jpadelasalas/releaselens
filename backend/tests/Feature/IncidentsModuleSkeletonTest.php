<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncidentsModuleSkeletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_incident_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('incidents'));
        $this->assertTrue(Schema::hasColumns('incidents', [
            'id', 'organization_id', 'title', 'summary', 'severity', 'state',
            'started_at', 'resolved_at', 'closed_at', 'created_by_user_id',
        ]));

        $this->assertTrue(Schema::hasTable('incident_links'));
        $this->assertTrue(Schema::hasColumns('incident_links', ['incident_id', 'linkable_type', 'linkable_id']));

        $this->assertTrue(Schema::hasTable('incident_timeline_entries'));
        $this->assertTrue(Schema::hasColumns('incident_timeline_entries', [
            'incident_id', 'actor_user_id', 'entry_type', 'message', 'occurred_at',
        ]));

        $this->assertTrue(Schema::hasTable('incident_action_items'));
        $this->assertTrue(Schema::hasColumns('incident_action_items', [
            'incident_id', 'description', 'assigned_to_user_id', 'is_completed',
            'completed_at', 'completed_by_user_id',
        ]));

        $this->assertTrue(Schema::hasTable('postmortems'));
        $this->assertTrue(Schema::hasColumns('postmortems', [
            'incident_id', 'summary', 'root_cause', 'impact', 'is_published', 'published_at',
        ]));
    }
}
