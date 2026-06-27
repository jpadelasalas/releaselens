<?php

namespace App\Modules\GitHub\Clients;

use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GitHubAppClient implements GitHubAppClientInterface
{
    public function installation(int $installationId): array
    {
        try {
            $response = Http::baseUrl((string) config('releaselens.github.api_url'))
                ->accept('application/vnd.github+json')
                ->withToken($this->appJwt())
                ->withHeaders([
                    'X-GitHub-Api-Version' => config('releaselens.github.api_version'),
                    'User-Agent' => config('releaselens.github.user_agent'),
                ])
                ->get("/app/installations/{$installationId}");
        } catch (ConnectionException) {
            throw new GitHubConnectionException(
                'GITHUB_API_UNAVAILABLE',
                'GitHub could not be reached to verify the installation.',
                503,
            );
        }

        if ($response->notFound()) {
            throw new GitHubConnectionException(
                'GITHUB_INSTALLATION_NOT_FOUND',
                'The GitHub installation no longer exists.',
                404,
            );
        }

        if ($response->failed()) {
            throw new GitHubConnectionException(
                'GITHUB_INSTALLATION_UNAVAILABLE',
                'GitHub could not verify that installation. Try connecting again.',
                502,
            );
        }

        return $response->json();
    }

    public function installationRepositories(int $installationId): array
    {
        try {
            $tokenResponse = Http::baseUrl((string) config('releaselens.github.api_url'))
                ->accept('application/vnd.github+json')
                ->withToken($this->appJwt())
                ->withHeaders($this->standardHeaders())
                ->post("/app/installations/{$installationId}/access_tokens");
        } catch (ConnectionException) {
            $this->throwUnavailable();
        }

        if ($tokenResponse->notFound()) {
            $this->throwInstallationNotFound();
        }

        if ($tokenResponse->failed() || ! is_string($tokenResponse->json('token'))) {
            $this->throwUnavailable();
        }

        $token = $tokenResponse->json('token');
        $repositories = [];
        $pageLimit = max(
            1,
            (int) config('releaselens.github.repository_page_limit'),
        );

        for ($page = 1; $page <= $pageLimit; $page++) {
            try {
                $response = Http::baseUrl((string) config('releaselens.github.api_url'))
                    ->accept('application/vnd.github+json')
                    ->withToken($token)
                    ->withHeaders($this->standardHeaders())
                    ->get('/installation/repositories', [
                        'per_page' => 100,
                        'page' => $page,
                    ]);
            } catch (ConnectionException) {
                $this->throwUnavailable();
            }

            if ($response->failed()) {
                $this->throwUnavailable();
            }

            $pageRepositories = $response->json('repositories');

            if (! is_array($pageRepositories)) {
                $this->throwUnavailable();
            }

            array_push($repositories, ...$pageRepositories);

            if (count($pageRepositories) < 100) {
                break;
            }
        }

        return $repositories;
    }

    private function appJwt(): string
    {
        $appId = (string) config('releaselens.github.app_id');
        $privateKey = $this->privateKey();

        if ($appId === '' || $privateKey === '') {
            throw new GitHubConnectionException(
                'GITHUB_APP_NOT_CONFIGURED',
                'GitHub connection is not configured for this environment.',
                503,
            );
        }

        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'iat' => $now - 60,
            'exp' => $now + 540,
            'iss' => $appId,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = "{$header}.{$payload}";
        $signed = openssl_sign(
            $unsignedToken,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256,
        );

        if (! $signed) {
            throw new GitHubConnectionException(
                'GITHUB_APP_CONFIGURATION_INVALID',
                'GitHub connection credentials are invalid.',
                503,
            );
        }

        return $unsignedToken.'.'.$this->base64Url($signature);
    }

    private function privateKey(): string
    {
        $encoded = (string) config('releaselens.github.private_key_base64');

        if ($encoded !== '') {
            return base64_decode($encoded, true) ?: '';
        }

        $path = (string) config('releaselens.github.private_key_path');

        if ($path !== '' && is_readable($path)) {
            return (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", (string) config('releaselens.github.private_key'));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @return array<string, string> */
    private function standardHeaders(): array
    {
        return [
            'X-GitHub-Api-Version' => (string) config('releaselens.github.api_version'),
            'User-Agent' => (string) config('releaselens.github.user_agent'),
        ];
    }

    private function throwInstallationNotFound(): never
    {
        throw new GitHubConnectionException(
            'GITHUB_INSTALLATION_NOT_FOUND',
            'The GitHub installation no longer exists.',
            404,
        );
    }

    private function throwUnavailable(): never
    {
        throw new GitHubConnectionException(
            'GITHUB_API_UNAVAILABLE',
            'GitHub could not be reached to load installation repositories.',
            503,
        );
    }
}
