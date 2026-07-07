<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookPullRequestHandlingTest extends TestCase
{
    use RefreshDatabase;

    private int $githubRepositoryId = 88_000_001;

    public function test_pull_request_opened_creates_a_local_record(): void
    {
        $repositoryId = $this->repository();
        $payload = $this->pullRequestPayload(900001, 1, 'Add health check', '2026-07-01T00:00:00Z');

        $this->processDelivery('pull_request', 'opened', [
            'pull_request' => $payload,
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $stored = DB::table('pull_requests')->where('github_pull_request_id', 900001)->first();
        $this->assertNotNull($stored);
        $this->assertSame('Add health check', $stored->title);
        $this->assertSame($repositoryId, $stored->repository_id);
    }

    public function test_pull_request_edited_updates_the_existing_record_idempotently(): void
    {
        $this->repository();
        $this->processDelivery('pull_request', 'opened', [
            'pull_request' => $this->pullRequestPayload(900002, 2, 'Original title', '2026-07-01T00:00:00Z'),
            'repository' => ['id' => $this->githubRepositoryId],
        ]);
        $this->processDelivery('pull_request', 'edited', [
            'pull_request' => $this->pullRequestPayload(900002, 2, 'Updated title', '2026-07-01T01:00:00Z'),
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->assertSame(1, DB::table('pull_requests')->where('github_pull_request_id', 900002)->count());
        $this->assertSame('Updated title', DB::table('pull_requests')->where('github_pull_request_id', 900002)->value('title'));
    }

    public function test_out_of_order_delivery_does_not_regress_stored_state(): void
    {
        $this->repository();
        $this->processDelivery('pull_request', 'synchronize', [
            'pull_request' => $this->pullRequestPayload(900003, 3, 'Newer title', '2026-07-01T02:00:00Z'),
            'repository' => ['id' => $this->githubRepositoryId],
        ]);
        // A delayed, out-of-order redelivery of an older payload arrives after the newer one.
        $this->processDelivery('pull_request', 'edited', [
            'pull_request' => $this->pullRequestPayload(900003, 3, 'Stale title', '2026-07-01T00:00:00Z'),
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->assertSame('Newer title', DB::table('pull_requests')->where('github_pull_request_id', 900003)->value('title'));
    }

    public function test_unmonitored_repository_is_a_benign_no_op(): void
    {
        $this->processDelivery('pull_request', 'opened', [
            'pull_request' => $this->pullRequestPayload(900004, 4, 'Untracked repo PR', '2026-07-01T00:00:00Z'),
            'repository' => ['id' => 999_999_999],
        ]);

        $this->assertSame(0, DB::table('pull_requests')->where('github_pull_request_id', 900004)->count());
        $this->assertSame('processed', DB::table('webhook_deliveries')->latest('id')->value('status'));
    }

    public function test_pull_request_review_submitted_creates_the_review_and_the_pull_request(): void
    {
        $this->repository();
        $review = [
            'id' => 700001,
            'user' => ['id' => 111, 'login' => 'reviewer-one', 'type' => 'User'],
            'state' => 'APPROVED',
            'submitted_at' => '2026-07-01T01:00:00Z',
        ];

        $this->processDelivery('pull_request_review', 'submitted', [
            'pull_request' => $this->pullRequestPayload(900005, 5, 'Reviewed PR', '2026-07-01T00:00:00Z'),
            'review' => $review,
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $pullRequestRow = DB::table('pull_requests')->where('github_pull_request_id', 900005)->first();
        $this->assertNotNull($pullRequestRow);

        $reviewRow = DB::table('pull_request_reviews')->where('github_review_id', 700001)->first();
        $this->assertNotNull($reviewRow);
        $this->assertSame($pullRequestRow->id, $reviewRow->pull_request_id);
        $this->assertSame('approved', $reviewRow->state);
    }

    public function test_repeated_review_delivery_does_not_duplicate_the_review(): void
    {
        $this->repository();
        $delivery = [
            'pull_request' => $this->pullRequestPayload(900006, 6, 'Reviewed twice', '2026-07-01T00:00:00Z'),
            'review' => [
                'id' => 700002,
                'user' => ['id' => 112, 'login' => 'reviewer-two', 'type' => 'User'],
                'state' => 'approved',
                'submitted_at' => '2026-07-01T01:00:00Z',
            ],
            'repository' => ['id' => $this->githubRepositoryId],
        ];

        $this->processDelivery('pull_request_review', 'submitted', $delivery);
        $this->processDelivery('pull_request_review', 'submitted', $delivery);

        $this->assertSame(1, DB::table('pull_request_reviews')->where('github_review_id', 700002)->count());
    }

    public function test_missing_pull_request_payload_is_a_permanent_validation_failure(): void
    {
        $this->repository();

        $this->processDelivery('pull_request', 'opened', ['repository' => ['id' => $this->githubRepositoryId]]);

        $delivery = DB::table('webhook_deliveries')->latest('id')->first();
        $this->assertSame('dead_lettered', $delivery->status);
        $this->assertSame('validation', $delivery->error_category);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processDelivery(string $eventName, string $actionName, array $payload): void
    {
        $deliveries = app(WebhookDeliveryRepositoryInterface::class);
        $delivery = $deliveries->create([
            'github_delivery_id' => 'delivery-'.uniqid('', true),
            'event_name' => $eventName,
            'action_name' => $actionName,
            'payload_sha256' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        (new ProcessWebhookDeliveryJob($delivery->id, $payload))->handle(
            $deliveries,
            app(WebhookEventAllowlist::class),
            app(WebhookEventHandlerRegistry::class),
        );
    }

    private function repository(): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('repositories')->insertGetId([
            'organization_id' => $organizationId,
            'github_repository_id' => $this->githubRepositoryId,
            'name' => 'widgets',
            'full_name' => 'acme/widgets',
            'visibility' => 'private',
            'sync_enabled' => true,
            'sync_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullRequestPayload(int $githubPrId, int $number, string $title, string $updatedAt): array
    {
        return [
            'id' => $githubPrId,
            'number' => $number,
            'title' => $title,
            'html_url' => "https://github.com/acme/widgets/pull/{$number}",
            'state' => 'open',
            'draft' => false,
            'user' => ['id' => 500_000 + $number, 'login' => "author-{$number}", 'type' => 'User'],
            'base' => ['ref' => 'main'],
            'head' => ['ref' => "feature/{$number}"],
            'additions' => 10,
            'deletions' => 2,
            'changed_files' => 1,
            'commits' => 1,
            'comments' => 0,
            'created_at' => '2026-07-01T00:00:00Z',
            'updated_at' => $updatedAt,
            'closed_at' => null,
            'merged_at' => null,
        ];
    }
}
