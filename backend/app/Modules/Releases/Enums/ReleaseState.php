<?php

namespace App\Modules\Releases\Enums;

enum ReleaseState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Released = 'released';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
