<?php

namespace Tests\Feature;

use App\Modules\Releases\Contracts\ReleaseActivityRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseActivityRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_stores_action_and_metadata(): void
    {
        $releaseId = $this->release();

        $activity = $this->repository()->record($releaseId, null, 'created', ['title' => 'July release']);

        $this->assertSame('created', $activity->action);
        $this->assertSame(['title' => 'July release'], json_decode($activity->metadata, true));
        $this->assertNull($activity->actor_user_id);
    }

    public function test_for_release_returns_activities_in_chronological_order(): void
    {
        $releaseId = $this->release();
        $repository = $this->repository();

        $repository->record($releaseId, null, 'created');
        $repository->record($releaseId, null, 'state_changed', ['to' => 'in_review']);

        $activities = $repository->forRelease($releaseId);

        $this->assertSame(['created', 'state_changed'], $activities->pluck('action')->all());
    }

    private function repository(): ReleaseActivityRepositoryInterface
    {
        return app(ReleaseActivityRepositoryInterface::class);
    }

    private function release(): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return app(ReleaseRepositoryInterface::class)
            ->create($organizationId, ['title' => 'July release'])
            ->id;
    }
}
