<?php

namespace Tests\Feature;

use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookInstallationAndRepositoryHandlingTest extends TestCase
{
    use RefreshDatabase;

    private int $githubRepositoryId = 88_100_001;

    private int $githubInstallationId = 77_100_001;

    public function test_ping_is_processed_with_no_side_effects(): void
    {
        $this->processDelivery('ping', null, ['zen' => 'Keep it logically awesome.']);

        $this->assertSame('processed', DB::table('webhook_deliveries')->latest('id')->value('status'));
    }

    public function test_installation_suspend_and_unsuspend_toggle_suspended_at(): void
    {
        $this->installation();

        $this->processDelivery('installation', 'suspend', ['installation' => ['id' => $this->githubInstallationId]]);
        $this->assertNotNull($this->installationRow()->suspended_at);

        $this->processDelivery('installation', 'unsuspend', ['installation' => ['id' => $this->githubInstallationId]]);
        $this->assertNull($this->installationRow()->suspended_at);
    }

    public function test_installation_deleted_disconnects_and_disables_repository_sync(): void
    {
        $installationRecordId = $this->installation();
        $this->repository($installationRecordId);

        $this->processDelivery('installation', 'deleted', ['installation' => ['id' => $this->githubInstallationId]]);

        $this->assertNotNull($this->installationRow()->disconnected_at);
        $this->assertSame(
            0,
            DB::table('repositories')->where('github_installation_id', $installationRecordId)->where('sync_enabled', true)->count(),
        );
    }

    public function test_installation_event_for_an_unknown_installation_is_a_benign_no_op(): void
    {
        $this->processDelivery('installation', 'suspend', ['installation' => ['id' => 999_999_999]]);

        $this->assertSame('processed', DB::table('webhook_deliveries')->latest('id')->value('status'));
    }

    public function test_repository_renamed_updates_local_metadata(): void
    {
        $this->repository();

        $this->processDelivery('repository', 'renamed', [
            'repository' => ['id' => $this->githubRepositoryId, 'name' => 'widgets-v2', 'full_name' => 'acme/widgets-v2'],
        ]);

        $updated = DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->first();
        $this->assertSame('widgets-v2', $updated->name);
        $this->assertSame('acme/widgets-v2', $updated->full_name);
    }

    public function test_repository_archived_and_unarchived_toggle_the_flag(): void
    {
        $this->repository();

        $this->processDelivery('repository', 'archived', ['repository' => ['id' => $this->githubRepositoryId]]);
        $this->assertSame(1, DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->where('is_archived', true)->count());

        $this->processDelivery('repository', 'unarchived', ['repository' => ['id' => $this->githubRepositoryId]]);
        $this->assertSame(1, DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->where('is_archived', false)->count());
    }

    public function test_repository_deleted_marks_it_inaccessible(): void
    {
        $this->repository();

        $this->processDelivery('repository', 'deleted', ['repository' => ['id' => $this->githubRepositoryId]]);

        $updated = DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->first();
        $this->assertFalse((bool) $updated->is_accessible);
        $this->assertSame('repository_deleted', $updated->access_error);
    }

    public function test_repository_event_for_an_unmonitored_repository_is_a_benign_no_op(): void
    {
        $this->processDelivery('repository', 'archived', ['repository' => ['id' => 999_999_999]]);

        $this->assertSame('processed', DB::table('webhook_deliveries')->latest('id')->value('status'));
    }

    public function test_installation_repositories_removed_and_added_toggle_accessibility(): void
    {
        $this->repository();

        $this->processDelivery('installation_repositories', 'removed', [
            'repositories_removed' => [['id' => $this->githubRepositoryId, 'name' => 'widgets']],
        ]);
        $removed = DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->first();
        $this->assertFalse((bool) $removed->is_accessible);
        $this->assertSame('installation_access_removed', $removed->access_error);

        $this->processDelivery('installation_repositories', 'added', [
            'repositories_added' => [['id' => $this->githubRepositoryId, 'name' => 'widgets']],
        ]);
        $added = DB::table('repositories')->where('github_repository_id', $this->githubRepositoryId)->first();
        $this->assertTrue((bool) $added->is_accessible);
        $this->assertNull($added->access_error);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processDelivery(string $eventName, ?string $actionName, array $payload): void
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

    private function installation(): int
    {
        $organizationId = (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('github_installations')->insertGetId([
            'organization_id' => $organizationId,
            'github_installation_id' => $this->githubInstallationId,
            'github_account_login' => 'acme',
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function installationRow(): object
    {
        return DB::table('github_installations')
            ->where('github_installation_id', $this->githubInstallationId)
            ->firstOrFail();
    }

    private function repository(?int $installationRecordId = null): int
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
            'github_installation_id' => $installationRecordId,
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
}
