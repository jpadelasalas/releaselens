<?php

namespace Tests\Feature;

use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleasePolicyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_for_organization_returns_null_when_no_policy_exists(): void
    {
        $organizationId = $this->organization();

        $this->assertNull($this->repository()->getForOrganization($organizationId));
    }

    public function test_upsert_creates_a_policy_with_defaults(): void
    {
        $organizationId = $this->organization();

        $policy = $this->repository()->upsertForOrganization($organizationId, []);

        $this->assertSame('single_approver', $policy->approval_mode);
        $this->assertSame(0, (int) $policy->allow_self_approval);
    }

    public function test_upsert_updates_an_existing_policy_instead_of_duplicating(): void
    {
        $organizationId = $this->organization();
        $repository = $this->repository();

        $repository->upsertForOrganization($organizationId, ['approval_mode' => 'none']);
        $repository->upsertForOrganization($organizationId, ['allow_self_approval' => true]);

        $policy = $repository->getForOrganization($organizationId);

        $this->assertSame('none', $policy->approval_mode);
        $this->assertSame(1, (int) $policy->allow_self_approval);
        $this->assertSame(1, DB::table('release_policies')->where('organization_id', $organizationId)->count());
    }

    private function repository(): ReleasePolicyRepositoryInterface
    {
        return app(ReleasePolicyRepositoryInterface::class);
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
}
