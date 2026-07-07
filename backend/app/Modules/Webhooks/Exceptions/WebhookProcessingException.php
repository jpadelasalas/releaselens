<?php

namespace App\Modules\Webhooks\Exceptions;

use RuntimeException;

class WebhookProcessingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category,
        public readonly bool $retryable,
    ) {
        parent::__construct($message);
    }
}
