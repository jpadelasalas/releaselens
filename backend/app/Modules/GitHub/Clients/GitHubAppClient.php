<?php

namespace App\Modules\GitHub\Clients;

use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Exceptions\GitHubConnectionException;
use Illuminate\Support\Facades\Http;

class GitHubAppClient implements GitHubAppClientInterface
{
    public function installation(int $installationId): array
    {
        $response = Http::baseUrl((string) config('releaselens.github.api_url'))
            ->accept('application/vnd.github+json')
            ->withToken($this->appJwt())
            ->withHeaders([
                'X-GitHub-Api-Version' => config('releaselens.github.api_version'),
                'User-Agent' => config('releaselens.github.user_agent'),
            ])
            ->get("/app/installations/{$installationId}");

        if ($response->failed()) {
            throw new GitHubConnectionException(
                'GITHUB_INSTALLATION_UNAVAILABLE',
                'GitHub could not verify that installation. Try connecting again.',
                502,
            );
        }

        return $response->json();
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
}
