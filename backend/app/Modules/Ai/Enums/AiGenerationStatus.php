<?php

namespace App\Modules\Ai\Enums;

enum AiGenerationStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
