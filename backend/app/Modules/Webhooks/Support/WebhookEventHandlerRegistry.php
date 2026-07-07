<?php

namespace App\Modules\Webhooks\Support;

use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;
use App\Modules\Webhooks\Exceptions\WebhookProcessingException;

/**
 * Resolves an allowlisted event to its handler. Empty until T1.4/T1.5
 * register the pull_request/review/installation/repository handlers;
 * an allowlisted event with no registered handler is a permanent
 * (not-retryable) failure rather than a silent success.
 */
class WebhookEventHandlerRegistry
{
    /**
     * @var array<string, WebhookEventHandlerInterface|class-string<WebhookEventHandlerInterface>>
     */
    private array $handlers = [];

    public function register(string $eventName, WebhookEventHandlerInterface|string $handler): void
    {
        $this->handlers[$eventName] = $handler;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventName, object $delivery, array $payload): void
    {
        if (! isset($this->handlers[$eventName])) {
            throw new WebhookProcessingException(
                "No handler is registered for the '{$eventName}' event yet.",
                category: 'handler_not_implemented',
                retryable: false,
            );
        }

        $handler = $this->handlers[$eventName];
        $instance = is_string($handler) ? app($handler) : $handler;
        $instance->handle($delivery, $payload);
    }
}
