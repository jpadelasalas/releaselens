<?php

namespace App\Modules\Releases\Support;

use App\Modules\Releases\Enums\ReleaseState;

class ReleaseStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'draft' => ['in_review', 'cancelled'],
        'in_review' => ['draft', 'approved', 'cancelled'],
        'approved' => ['in_review', 'released', 'cancelled'],
        'released' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public static function canTransition(ReleaseState $from, ReleaseState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return array<int, ReleaseState>
     */
    public static function allowedTransitions(ReleaseState $from): array
    {
        return array_map(
            fn (string $state): ReleaseState => ReleaseState::from($state),
            self::TRANSITIONS[$from->value],
        );
    }
}
