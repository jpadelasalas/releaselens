<?php

namespace App\Modules\Notifications\Enums;

enum NotificationType: string
{
    case ReleaseApprovalRequired = 'release.approval_required';
    case ReleaseReleased = 'release.released';
    case DeploymentFailed = 'deployment.failed';
    case DeploymentRolledBack = 'deployment.rolled_back';
}
