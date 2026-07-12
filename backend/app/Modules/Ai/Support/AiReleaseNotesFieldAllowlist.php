<?php

namespace App\Modules\Ai\Support;

use Illuminate\Support\Collection;

/**
 * Restricts what a release's data can send to an AI provider. Only these
 * fields are ever placed in the outgoing context, regardless of what else
 * is available on the release/pull-request objects (organization_id,
 * created_by_user_id, approvals, timestamps, etc. are never included).
 */
class AiReleaseNotesFieldAllowlist
{
    /**
     * @return array<int, string>
     */
    public static function fields(): array
    {
        return ['title', 'description', 'pull_request_titles'];
    }

    /**
     * @return array{title: string, description: string, pull_request_titles: array<int, string>}
     */
    public static function extract(object $release, Collection $pullRequests): array
    {
        return [
            'title' => (string) $release->title,
            'description' => (string) ($release->description ?? ''),
            'pull_request_titles' => $pullRequests
                ->map(fn (object $pullRequest): string => (string) $pullRequest->title)
                ->values()
                ->all(),
        ];
    }
}
