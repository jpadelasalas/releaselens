<?php

namespace App\Modules\Incidents\Support;

use App\Modules\Incidents\Enums\IncidentState;

class IncidentStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'investigating' => ['identified'],
        'identified' => ['monitoring', 'investigating'],
        'monitoring' => ['resolved', 'identified'],
        'resolved' => ['closed', 'monitoring'],
        'closed' => [],
    ];

    public static function canTransition(IncidentState $from, IncidentState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return array<int, IncidentState>
     */
    public static function allowedTransitions(IncidentState $from): array
    {
        return array_map(
            fn (string $state): IncidentState => IncidentState::from($state),
            self::TRANSITIONS[$from->value],
        );
    }
}
