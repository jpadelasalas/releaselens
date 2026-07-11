<?php

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AiReleaseNotesProviderInterface;

/**
 * Deterministic, zero-external-call provider. The allowlisted context is
 * treated purely as inert data to template into Markdown — never as
 * instructions to interpret — so no prompt-injection payload embedded in
 * a release title/description/PR title can change what this method does.
 *
 * This is the only provider wired up today; a real LLM-backed provider
 * would implement the same interface and get bound in its place without
 * any change to AiReleaseNotesService or the domain model.
 */
class StubAiReleaseNotesProvider implements AiReleaseNotesProviderInterface
{
    public function name(): string
    {
        return 'stub';
    }

    public function generate(array $context): string
    {
        $lines = ["# {$context['title']}"];

        if ($context['description'] !== '') {
            $lines[] = '';
            $lines[] = $context['description'];
        }

        if ($context['pull_request_titles'] !== []) {
            $lines[] = '';
            $lines[] = '## Changes';
            foreach ($context['pull_request_titles'] as $title) {
                $lines[] = "- {$title}";
            }
        }

        return implode("\n", $lines);
    }
}
