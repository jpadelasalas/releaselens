<?php

namespace App\Modules\Synchronization\Services;

use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use App\Modules\Synchronization\Contracts\GitHubRepositorySyncClientInterface;
use App\Modules\Synchronization\Exceptions\SynchronizationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GitHubRepositorySyncClient implements GitHubRepositorySyncClientInterface
{
    private ?int $rateLimitRemaining = null;

    private ?string $rateLimitResetAt = null;

    public function __construct(
        private readonly GitHubAppClientInterface $github,
    ) {}

    public function synchronize(
        int $installationId,
        string $repositoryFullName,
        ?string $cursor,
    ): array {
        try {
            $token = $this->github->installationAccessToken($installationId);
        } catch (GitHubConnectionException $exception) {
            throw new SynchronizationException(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->status >= 500,
            );
        }

        $encodedRepository = implode('/', array_map(
            'rawurlencode',
            explode('/', $repositoryFullName, 2),
        ));
        $url = rtrim((string) config('releaselens.github.api_url'), '/')
            ."/repos/{$encodedRepository}/pulls";
        $params = [
            'state' => 'all',
            'sort' => 'updated',
            'direction' => 'desc',
            'per_page' => 100,
        ];
        $cutoff = now()->subDays(
            (int) config('releaselens.github.initial_sync_lookback_days'),
        );
        $limit = max(1, (int) config('releaselens.github.sync_pull_request_limit'));
        $items = [];
        $cursorAfter = $cursor;
        $stop = false;

        while ($url !== null && count($items) < $limit && ! $stop) {
            $response = $this->request($token, $url, $params);
            $params = [];
            $pullRequests = $response->json();

            if (! is_array($pullRequests)) {
                throw new SynchronizationException(
                    'invalid_response',
                    'GitHub returned invalid pull-request data.',
                );
            }

            foreach ($pullRequests as $pullRequest) {
                $updatedAt = (string) ($pullRequest['updated_at'] ?? '');

                if ($updatedAt === '') {
                    continue;
                }

                if ($cursor !== null && $updatedAt <= $cursor) {
                    $stop = true;
                    break;
                }

                if ($cursor === null && CarbonImmutable::parse($updatedAt)->lt($cutoff)) {
                    $stop = true;
                    break;
                }

                $number = (int) ($pullRequest['number'] ?? 0);

                if ($number <= 0) {
                    continue;
                }

                $detail = $this->request(
                    $token,
                    rtrim((string) config('releaselens.github.api_url'), '/')
                        ."/repos/{$encodedRepository}/pulls/{$number}",
                )->json();
                $reviews = $this->reviews(
                    $token,
                    $encodedRepository,
                    $number,
                );
                $items[] = ['pull_request' => $detail, 'reviews' => $reviews];
                $cursorAfter = $cursorAfter === null || $updatedAt > $cursorAfter
                    ? $updatedAt
                    : $cursorAfter;

                if (count($items) >= $limit) {
                    break;
                }
            }

            $url = $this->nextLink($response);
        }

        return [
            'items' => $items,
            'cursor_after' => $cursorAfter,
            'rate_limit_remaining' => $this->rateLimitRemaining,
            'rate_limit_reset_at' => $this->rateLimitResetAt,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function reviews(string $token, string $repository, int $number): array
    {
        $url = rtrim((string) config('releaselens.github.api_url'), '/')
            ."/repos/{$repository}/pulls/{$number}/reviews";
        $params = ['per_page' => 100];
        $reviews = [];

        while ($url !== null) {
            $response = $this->request($token, $url, $params);
            $params = [];
            $page = $response->json();

            if (! is_array($page)) {
                throw new SynchronizationException(
                    'invalid_response',
                    'GitHub returned invalid review data.',
                );
            }

            array_push($reviews, ...$page);
            $url = $this->nextLink($response);
        }

        return $reviews;
    }

    /** @param array<string, mixed> $query */
    private function request(string $token, string $url, array $query = []): Response
    {
        try {
            $response = Http::accept('application/vnd.github+json')
                ->withToken($token)
                ->withHeaders([
                    'X-GitHub-Api-Version' => config('releaselens.github.api_version'),
                    'User-Agent' => config('releaselens.github.user_agent'),
                ])
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new SynchronizationException(
                'network',
                'GitHub could not be reached during synchronization.',
                true,
            );
        }

        $this->captureRateLimit($response);

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $category = match (true) {
            $status === 401 => 'authentication',
            $status === 403 => 'permission_or_rate_limit',
            $status === 404 => 'not_found',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'github_unavailable',
            default => 'github_request',
        };

        throw new SynchronizationException(
            $category,
            "GitHub synchronization request failed with status {$status}.",
            $status >= 500 || $status === 429,
        );
    }

    private function captureRateLimit(Response $response): void
    {
        $remaining = $response->header('X-RateLimit-Remaining');
        $reset = $response->header('X-RateLimit-Reset');

        if (is_numeric($remaining)) {
            $this->rateLimitRemaining = (int) $remaining;
        }

        if (is_numeric($reset)) {
            $this->rateLimitResetAt = CarbonImmutable::createFromTimestampUTC(
                (int) $reset,
            )->toIso8601String();
        }
    }

    private function nextLink(Response $response): ?string
    {
        $link = $response->header('Link');

        if (! is_string($link)) {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (str_contains($part, 'rel="next"') &&
                preg_match('/<([^>]+)>/', $part, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
