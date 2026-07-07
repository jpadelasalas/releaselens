<?php

namespace App\Modules\Webhooks\Handlers;

use App\Modules\Webhooks\Contracts\WebhookEventHandlerInterface;

/**
 * The ping event is a connection-verification check with no domain
 * data (V2-FR-WH-011). It always succeeds and does nothing.
 */
class PingWebhookHandler implements WebhookEventHandlerInterface
{
    public function handle(object $delivery, array $payload): void {}
}
