<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GitHubWebhookIngressTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('releaselens.features.webhooks', true);
        config()->set('releaselens.github.webhook_secret', self::SECRET);
    }

    public function test_valid_signature_and_new_delivery_is_accepted_and_queued(): void
    {
        Queue::fake();

        $body = json_encode(['action' => 'opened', 'hook_id' => 123], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-1',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($body),
        ]);

        $response->assertStatus(202)->assertJsonPath('data.status', 'accepted');

        $delivery = DB::table('webhook_deliveries')->where('github_delivery_id', 'delivery-1')->first();
        $this->assertNotNull($delivery);
        $this->assertSame('queued', $delivery->status);
        $this->assertSame('pull_request', $delivery->event_name);
        $this->assertSame('opened', $delivery->action_name);
        $this->assertSame(123, $delivery->github_hook_id);

        Queue::assertPushed(
            ProcessWebhookDeliveryJob::class,
            fn (ProcessWebhookDeliveryJob $job): bool => $job->webhookDeliveryId === $delivery->id,
        );
    }

    public function test_invalid_signature_is_rejected_without_creating_a_delivery(): void
    {
        $body = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-invalid',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'wrong-secret'),
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');
        $this->assertSame(0, DB::table('webhook_deliveries')->count());
    }

    public function test_truncated_signature_is_rejected(): void
    {
        $body = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);
        $signature = $this->signatureFor($body);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-truncated',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => substr($signature, 0, -4),
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');
        $this->assertSame(0, DB::table('webhook_deliveries')->count());
    }

    public function test_missing_delivery_header_returns_400(): void
    {
        $body = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($body),
        ]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'WEBHOOK_HEADERS_INVALID');
    }

    public function test_missing_event_header_returns_400(): void
    {
        $body = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-no-event',
            'X-Hub-Signature-256' => $this->signatureFor($body),
        ]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'WEBHOOK_HEADERS_INVALID');
    }

    public function test_missing_signature_header_returns_401(): void
    {
        $body = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-no-signature',
            'X-GitHub-Event' => 'pull_request',
        ]);

        $response->assertStatus(401)->assertJsonPath('error.code', 'WEBHOOK_SIGNATURE_INVALID');
    }

    public function test_malformed_json_payload_is_rejected_even_with_a_valid_signature(): void
    {
        $body = 'not-json';

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-malformed',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($body),
        ]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'WEBHOOK_HEADERS_INVALID');
        $this->assertSame(0, DB::table('webhook_deliveries')->count());
    }

    public function test_oversized_payload_is_rejected(): void
    {
        config()->set('releaselens.github.webhook_max_payload_bytes', 10);

        $body = json_encode(['action' => 'opened', 'padding' => str_repeat('x', 100)], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, [
            'X-GitHub-Delivery' => 'delivery-oversized',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($body),
        ]);

        $response->assertStatus(400)->assertJsonPath('error.code', 'WEBHOOK_HEADERS_INVALID');
    }

    public function test_duplicate_delivery_id_is_accepted_without_a_second_record_or_dispatch(): void
    {
        Queue::fake();

        $firstBody = json_encode(['action' => 'opened'], JSON_THROW_ON_ERROR);
        $this->postWebhook($firstBody, [
            'X-GitHub-Delivery' => 'delivery-duplicate',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($firstBody),
        ])->assertStatus(202);

        $secondBody = json_encode(['action' => 'synchronize'], JSON_THROW_ON_ERROR);
        $response = $this->postWebhook($secondBody, [
            'X-GitHub-Delivery' => 'delivery-duplicate',
            'X-GitHub-Event' => 'pull_request',
            'X-Hub-Signature-256' => $this->signatureFor($secondBody),
        ]);

        $response->assertStatus(202)->assertJsonPath('data.status', 'duplicate');
        $this->assertSame(1, DB::table('webhook_deliveries')->where('github_delivery_id', 'delivery-duplicate')->count());
        Queue::assertPushed(ProcessWebhookDeliveryJob::class, 1);
    }

    public function test_disabled_feature_flag_returns_404_before_signature_verification(): void
    {
        config()->set('releaselens.features.webhooks', false);

        $response = $this->postWebhook('{}', [
            'X-GitHub-Delivery' => 'delivery-disabled',
            'X-GitHub-Event' => 'ping',
            'X-Hub-Signature-256' => 'sha256=irrelevant',
        ]);

        $response->assertStatus(404)->assertJsonPath('error.code', 'FEATURE_DISABLED');
        $this->assertSame(0, DB::table('webhook_deliveries')->count());
    }

    private function signatureFor(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, self::SECRET);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function postWebhook(string $rawBody, array $headers): TestResponse
    {
        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            ...$headers,
        ]);

        return $this->call('POST', '/api/v1/github/webhooks', [], [], [], $server, $rawBody);
    }
}
