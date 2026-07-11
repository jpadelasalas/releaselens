<?php

namespace App\Modules\Incidents\Enums;

enum IncidentState: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
