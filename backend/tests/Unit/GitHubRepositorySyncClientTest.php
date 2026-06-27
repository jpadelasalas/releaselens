<?php

namespace Tests\Unit;

use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\Synchronization\Exceptions\SynchronizationException;
use App\Modules\Synchronization\Services\GitHubRepositorySyncClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTokenGitHubClient implements GitHubAppClientInterface
{
    public function installation(int $installationId): array
    {
        return [];
    }

    public function installationRepositories(int $installationId): array
    {
        return [];
    }

    public function installationAccessToken(int $installationId): string
    {
        return 'installation-token';
    }
}

class GitHubRepositorySyncClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'releaselens.github.api_url' => 'https://api.github.test',
            'releaselens.github.api_version' => '2026-03-10',
            'releaselens.github.user_agent' => 'ReleaseLens/Test',
            'releaselens.github.initial_sync_lookback_days' => 90,
            'releaselens.github.sync_pull_request_limit' => 200,
        ]);
    }

    public function test_it_fetches_repository_pr_details_and_reviews_with_pinned_headers(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/repos/acme/api' => Http::response([
                    'id' => 100,
                    'name' => 'api',
                    'full_name' => 'acme/api',
                ], 200, $this->rateHeaders()),
                $path === '/repos/acme/api/pulls' => Http::response([[
                    'number' => 42,
                    'updated_at' => now()->subDay()->toIso8601String(),
                ]], 200, $this->rateHeaders()),
                $path === '/repos/acme/api/pulls/42' => Http::response([
                    'id' => 7001,
                    'number' => 42,
                    'updated_at' => now()->subDay()->toIso8601String(),
                ], 200, $this->rateHeaders()),
                $path === '/repos/acme/api/pulls/42/reviews' => Http::response([
                    ['id' => 8001, 'state' => 'APPROVED'],
                ], 200, $this->rateHeaders()),
                default => Http::response([], 404),
            };
        });

        $result = (new GitHubRepositorySyncClient(new SyncTokenGitHubClient))
            ->synchronize(10, 'acme/api', null);

        $this->assertCount(1, $result['items']);
        $this->assertSame(8001, $result['items'][0]['reviews'][0]['id']);
        $this->assertSame(4990, $result['rate_limit_remaining']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-GitHub-Api-Version', '2026-03-10') &&
            $request->hasHeader('User-Agent', 'ReleaseLens/Test'));
    }

    public function test_incremental_cursor_stops_before_fetching_unchanged_details(): void
    {
        Http::fake([
            'https://api.github.test/repos/acme/api' => Http::response([
                'id' => 100,
                'name' => 'api',
                'full_name' => 'acme/api',
            ]),
            'https://api.github.test/repos/acme/api/pulls*' => Http::response([[
                'number' => 42,
                'updated_at' => '2026-06-20T00:00:00Z',
            ]]),
        ]);

        $result = (new GitHubRepositorySyncClient(new SyncTokenGitHubClient))
            ->synchronize(10, 'acme/api', '2026-06-21T00:00:00Z');

        $this->assertSame([], $result['items']);
        Http::assertSentCount(2);
    }

    public function test_not_found_is_classified_as_permanent(): void
    {
        Http::fake([
            '*' => Http::response([], 404),
        ]);

        try {
            (new GitHubRepositorySyncClient(new SyncTokenGitHubClient))
                ->synchronize(10, 'acme/missing', null);
            $this->fail('Expected synchronization exception was not thrown.');
        } catch (SynchronizationException $exception) {
            $this->assertSame('not_found', $exception->category);
            $this->assertFalse($exception->retryable);
        }
    }

    /** @return array<string, string> */
    private function rateHeaders(): array
    {
        return [
            'X-RateLimit-Remaining' => '4990',
            'X-RateLimit-Reset' => (string) now()->addHour()->timestamp,
        ];
    }
}
