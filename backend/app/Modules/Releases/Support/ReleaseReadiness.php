<?php

namespace App\Modules\Releases\Support;

use App\Modules\Releases\Enums\ReleaseState;
use Illuminate\Support\Collection;

class ReleaseReadiness
{
    /**
     * @return array<int, array{code: string, message: string}>
     */
    public static function warnings(object $release, Collection $checklistItems, Collection $pullRequests, Collection $repositories): array
    {
        $warnings = [];

        if ($pullRequests->isEmpty()) {
            $warnings[] = ['code' => 'no_pull_requests', 'message' => 'No pull requests are included in this release.'];
        }

        if ($checklistItems->contains(fn (object $item): bool => (bool) $item->is_required && $item->completed_at === null)) {
            $warnings[] = ['code' => 'incomplete_required_checklist', 'message' => 'Required checklist items are not yet complete.'];
        }

        if ($repositories->contains(fn (object $repository): bool => ! (bool) $repository->is_accessible)) {
            $warnings[] = ['code' => 'inaccessible_repository', 'message' => 'A repository included in this release is no longer accessible.'];
        }

        if (
            $release->target_release_at !== null &&
            now()->greaterThan($release->target_release_at) &&
            ! in_array($release->state, [ReleaseState::Released->value, ReleaseState::Closed->value, ReleaseState::Cancelled->value], true)
        ) {
            $warnings[] = ['code' => 'past_target_date', 'message' => 'The target release date has passed.'];
        }

        return $warnings;
    }
}
