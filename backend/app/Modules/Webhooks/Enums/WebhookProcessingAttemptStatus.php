<?php

namespace App\Modules\Webhooks\Enums;

enum WebhookProcessingAttemptStatus: string
{
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
