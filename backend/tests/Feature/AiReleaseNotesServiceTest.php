<?php

namespace Tests\Feature;

use App\Modules\Ai\Contracts\AiGenerationRepositoryInterface;
use App\Modules\Ai\Exceptions\AiRuleException;
use App\Modules\Ai\Services\AiReleaseNotesService;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiReleaseNotesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_records_a_succeeded_audit_row(): void
    {
        $organizationId = $this->organization();
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, [
            'title' => 'July release',
            'description' => 'Stability improvements.',
        ]);

        $generation = app(AiReleaseNotesService::class)->generate($organizationId, $release, new Collection, null);

        $this->assertSame('succeeded', $generation->status);
        $this->assertSame('stub', $generation->provider);
        $this->assertStringContainsString('# July release', $generation->output);
        $this->assertSame(
            ['title', 'description', 'pull_request_titles'],
            json_decode($generation->input_fields, true),
        );
    }

    public function test_generate_throws_once_the_monthly_limit_is_reached(): void
    {
        config()->set('releaselens.ai.monthly_generation_limit', 1);
        $organizationId = $this->organization();
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        $service = app(AiReleaseNotesService::class);

        $service->generate($organizationId, $release, new Collection, null);

        $this->expectException(AiRuleException::class);
        $this->expectExceptionMessage('This organization has reached its monthly limit of 1 AI generations.');

        $service->generate($organizationId, $release, new Collection, null);
    }

    public function test_generation_count_is_scoped_to_the_organization(): void
    {
        config()->set('releaselens.ai.monthly_generation_limit', 1);
        $organizationA = $this->organization();
        $organizationB = $this->organization();
        $releaseA = app(ReleaseRepositoryInterface::class)->create($organizationA, ['title' => 'A release']);
        $releaseB = app(ReleaseRepositoryInterface::class)->create($organizationB, ['title' => 'B release']);
        $service = app(AiReleaseNotesService::class);

        $service->generate($organizationA, $releaseA, new Collection, null);

        $generation = $service->generate($organizationB, $releaseB, new Collection, null);

        $this->assertSame('succeeded', $generation->status);
    }

    public function test_generations_are_queryable_per_release(): void
    {
        $organizationId = $this->organization();
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);
        app(AiReleaseNotesService::class)->generate($organizationId, $release, new Collection, null);

        $generations = app(AiGenerationRepositoryInterface::class)->forRelease($release->id);

        $this->assertCount(1, $generations);
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
