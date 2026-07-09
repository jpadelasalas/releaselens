<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReleasesModuleSkeletonTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_release_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('releases'));
        $this->assertTrue(Schema::hasColumns('releases', [
            'id', 'organization_id', 'title', 'description', 'state',
            'target_release_at', 'released_at', 'closed_at', 'created_by_user_id',
        ]));

        $this->assertTrue(Schema::hasTable('release_repositories'));
        $this->assertTrue(Schema::hasColumns('release_repositories', ['release_id', 'repository_id']));

        $this->assertTrue(Schema::hasTable('release_pull_requests'));
        $this->assertTrue(Schema::hasColumns('release_pull_requests', ['release_id', 'pull_request_id', 'added_by_user_id']));

        $this->assertTrue(Schema::hasTable('release_checklist_items'));
        $this->assertTrue(Schema::hasColumns('release_checklist_items', [
            'release_id', 'label', 'is_required', 'position', 'completed_at', 'completed_by_user_id',
        ]));

        $this->assertTrue(Schema::hasTable('release_activities'));
        $this->assertTrue(Schema::hasColumns('release_activities', [
            'release_id', 'actor_user_id', 'action', 'metadata', 'occurred_at',
        ]));

        $this->assertTrue(Schema::hasTable('release_approvals'));
        $this->assertTrue(Schema::hasColumns('release_approvals', ['release_id', 'approver_user_id', 'approved_at']));

        $this->assertTrue(Schema::hasTable('release_policies'));
        $this->assertTrue(Schema::hasColumns('release_policies', [
            'organization_id', 'approval_mode', 'allow_self_approval',
        ]));
    }
}
