<?php

namespace App\Modules\Ai\Contracts;

interface AiReleaseNotesProviderInterface
{
    /**
     * @param  array{title: string, description: string, pull_request_titles: array<int, string>}  $context
     */
    public function generate(array $context): string;

    public function name(): string;
}
