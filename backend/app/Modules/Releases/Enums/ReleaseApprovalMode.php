<?php

namespace App\Modules\Releases\Enums;

enum ReleaseApprovalMode: string
{
    case None = 'none';
    case SingleApprover = 'single_approver';
}
