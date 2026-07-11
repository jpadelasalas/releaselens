<?php

namespace App\Modules\Deployments\Enums;

enum DeploymentStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Success = 'success';
    case Failure = 'failure';
    case Error = 'error';
    case Inactive = 'inactive';
}
