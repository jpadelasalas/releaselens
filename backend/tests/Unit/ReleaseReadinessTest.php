<?php

namespace Tests\Unit;

use App\Modules\Releases\Support\ReleaseReadiness;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ReleaseReadinessTest extends TestCase
{
    public function test_warns_when_no_pull_requests_are_included(): void
    {
        $release = (object) ['state' => 'draft', 'target_release_at' => null];

        $warnings = ReleaseReadiness::warnings($release, new Collection, new Collection, new Collection);

        $this->assertContains('no_pull_requests', array_column($warnings, 'code'));
    }

    public function test_warns_when_a_required_checklist_item_is_incomplete(): void
    {
        $release = (object) ['state' => 'draft', 'target_release_at' => null];
        $checklist = new Collection([(object) ['is_required' => true, 'completed_at' => null]]);
        $pullRequests = new Collection([(object) ['id' => 1]]);

        $warnings = ReleaseReadiness::warnings($release, $checklist, $pullRequests, new Collection);

        $this->assertContains('incomplete_required_checklist', array_column($warnings, 'code'));
    }

    public function test_no_warning_when_required_checklist_item_is_complete(): void
    {
        $release = (object) ['state' => 'draft', 'target_release_at' => null];
        $checklist = new Collection([(object) ['is_required' => true, 'completed_at' => now()]]);
        $pullRequests = new Collection([(object) ['id' => 1]]);

        $warnings = ReleaseReadiness::warnings($release, $checklist, $pullRequests, new Collection);

        $this->assertNotContains('incomplete_required_checklist', array_column($warnings, 'code'));
    }

    public function test_warns_when_a_repository_is_inaccessible(): void
    {
        $release = (object) ['state' => 'draft', 'target_release_at' => null];
        $repositories = new Collection([(object) ['is_accessible' => false]]);
        $pullRequests = new Collection([(object) ['id' => 1]]);

        $warnings = ReleaseReadiness::warnings($release, new Collection, $pullRequests, $repositories);

        $this->assertContains('inaccessible_repository', array_column($warnings, 'code'));
    }

    public function test_warns_when_the_target_date_has_passed_and_release_is_not_final(): void
    {
        $release = (object) ['state' => 'in_review', 'target_release_at' => now()->subDay()];
        $pullRequests = new Collection([(object) ['id' => 1]]);

        $warnings = ReleaseReadiness::warnings($release, new Collection, $pullRequests, new Collection);

        $this->assertContains('past_target_date', array_column($warnings, 'code'));
    }

    public function test_no_past_target_date_warning_once_released(): void
    {
        $release = (object) ['state' => 'released', 'target_release_at' => now()->subDay()];
        $pullRequests = new Collection([(object) ['id' => 1]]);

        $warnings = ReleaseReadiness::warnings($release, new Collection, $pullRequests, new Collection);

        $this->assertNotContains('past_target_date', array_column($warnings, 'code'));
    }
}
