<?php

namespace App\Modules\Analytics\Enums;

enum AnalyticsDateBasis: string
{
    case Created = 'created_at_github';
    case Merged = 'merged_at';
    case Closed = 'closed_at';
}
