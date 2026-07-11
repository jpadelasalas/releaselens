<?php

namespace App\Modules\Incidents\Enums;

enum IncidentSeverity: string
{
    case Sev1 = 'sev1';
    case Sev2 = 'sev2';
    case Sev3 = 'sev3';
    case Sev4 = 'sev4';

    /**
     * Sev1/Sev2 incidents require a published postmortem before closing.
     */
    public function requiresPostmortem(): bool
    {
        return $this === self::Sev1 || $this === self::Sev2;
    }
}
