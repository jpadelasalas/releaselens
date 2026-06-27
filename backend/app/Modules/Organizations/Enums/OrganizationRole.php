<?php

namespace App\Modules\Organizations\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Viewer = 'viewer';
}
