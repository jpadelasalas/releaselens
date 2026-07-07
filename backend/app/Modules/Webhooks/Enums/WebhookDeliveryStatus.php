<?php

namespace App\Modules\Webhooks\Enums;

enum WebhookDeliveryStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case RetryableFailed = 'retryable_failed';
    case DeadLettered = 'dead_lettered';
    case Purged = 'purged';
}
