<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseApprovalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_stores_the_approver_and_timestamp(): void
    {
        $releaseId = $this->release();
        $approver = $this->user();

        $approval = $this->repository()->record($releaseId, $approver->id);

        $this->assertSame($approver->id, $approval->approver_user_id);
        $this->assertNotNull($approval->approved_at);
    }

    public function test_for_release_returns_approvals_in_chronological_order(): void
    {
        $releaseId = $this->release();
        $first = $this->user();
        $second = $this->user();
        $repository = $this->repository();

        $repository->record($releaseId, $first->id);
        $repository->record($releaseId, $second->id);

        $approvals = $repository->forRelease($releaseId);

        $this->assertSame([$first->id, $second->id], $approvals->pluck('approver_user_id')->all());
    }

    private function repository(): ReleaseApprovalRepositoryInterface
    {
        return app(ReleaseApprovalRepositoryInterface::class);
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

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Approver',
            'email' => 'approver-'.uniqid('', true).'@example.com',
            'normalized_email' => 'approver-'.uniqid('', true).'@example.com',
            'password' => bcrypt('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }
}
