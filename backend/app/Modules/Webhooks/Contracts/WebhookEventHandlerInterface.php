<?php

namespace App\Modules\Webhooks\Contracts;

interface WebhookEventHandlerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(object $delivery, array $payload): void;
}
