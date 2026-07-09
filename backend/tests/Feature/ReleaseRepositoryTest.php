<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReleaseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_defaults_to_draft_state(): void
    {
        $organizationId = $this->organization();

        $release = $this->repository()->create($organizationId, [
            'title' => 'July release',
        ]);

        $this->assertSame('draft', $release->state);
        $this->assertSame($organizationId, $release->organization_id);
    }

    public function test_create_accepts_a_creator(): void
    {
        $organizationId = $this->organization();
        $user = $this->user();

        $release = $this->repository()->create($organizationId, [
            'title' => 'July release',
            'created_by_user_id' => $user->id,
        ]);

        $this->assertSame($user->id, $release->created_by_user_id);
    }

    public function test_find_for_organization_is_scoped(): void
    {
        $organizationA = $this->organization();
        $organizationB = $this->organization();

        $release = $this->repository()->create($organizationA, ['title' => 'Scoped release']);

        $this->assertNotNull($this->repository()->findForOrganization($organizationA, $release->id));
        $this->assertNull($this->repository()->findForOrganization($organizationB, $release->id));
    }

    private function repository(): ReleaseRepositoryInterface
    {
        return app(ReleaseRepositoryInterface::class);
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

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Release Author',
            'email' => 'author-'.uniqid('', true).'@example.com',
            'normalized_email' => 'author-'.uniqid('', true).'@example.com',
            'password' => bcrypt('release-lens-2026'),
            'timezone' => 'UTC',
        ]);
    }
}
