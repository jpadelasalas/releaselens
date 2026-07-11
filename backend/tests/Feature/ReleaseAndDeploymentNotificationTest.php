<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Services\ReleaseService;
use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Jobs\ProcessWebhookDeliveryJob;
use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReleaseAndDeploymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private int $githubRepositoryId = 66_000_001;

    public function test_moving_a_release_to_in_review_notifies_owners_and_managers_only(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $manager = $this->member($organizationId, 'manager');
        $viewer = $this->member($organizationId, 'viewer');
        $release = app(ReleaseRepositoryInterface::class)->create($organizationId, ['title' => 'July release']);

        app(ReleaseService::class)->transition($release, $owner->id, ReleaseState::InReview);

        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->where('type', 'release.approval_required')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $manager->id)->where('type', 'release.approval_required')->count());
        $this->assertSame(0, DB::table('notifications')->where('user_id', $viewer->id)->where('type', 'release.approval_required')->count());
    }

    public function test_releasing_notifies_all_organization_members(): void
    {
        $organizationId = $this->organization();
        $owner = $this->member($organizationId, 'owner');
        $viewer = $this->member($organizationId, 'viewer');
        $releases = app(ReleaseRepositoryInterface::class);
        $release = $releases->create($organizationId, ['title' => 'July release']);
        $releases->updateState($release->id, 'approved');
        $release = $releases->find($release->id);

        app(ReleaseService::class)->transition($release, $owner->id, ReleaseState::Released);

        $this->assertSame(1, DB::table('notifications')->where('user_id', $viewer->id)->where('type', 'release.released')->count());
    }

    public function test_a_failed_deployment_notifies_owners_and_managers(): void
    {
        $organizationId = $this->repository();
        $owner = $this->member($organizationId, 'owner');
        $viewer = $this->member($organizationId, 'viewer');

        $this->processDelivery('deployment', null, [
            'deployment' => ['id' => 600001, 'ref' => 'main', 'sha' => 'abc', 'environment' => 'production'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);
        $this->processDelivery('deployment_status', null, [
            'deployment' => ['id' => 600001],
            'deployment_status' => ['state' => 'failure'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->where('type', 'deployment.failed')->count());
        $this->assertSame(0, DB::table('notifications')->where('user_id', $viewer->id)->where('type', 'deployment.failed')->count());
    }

    public function test_a_deployment_rollback_notifies_owners_and_managers(): void
    {
        $organizationId = $this->repository();
        $owner = $this->member($organizationId, 'owner');

        $this->processDelivery('deployment', null, [
            'deployment' => ['id' => 600002, 'ref' => 'main', 'sha' => 'abc', 'environment' => 'production'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);
        $this->processDelivery('deployment_status', null, [
            'deployment' => ['id' => 600002],
            'deployment_status' => ['state' => 'success'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);
        $this->processDelivery('deployment_status', null, [
            'deployment' => ['id' => 600002],
            'deployment_status' => ['state' => 'inactive'],
            'repository' => ['id' => $this->githubRepositoryId],
        ]);

        $this->assertSame(1, DB::table('notifications')->where('user_id', $owner->id)->where('type', 'deployment.rolled_back')->count());
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

    private function organization(): int
    {
        return (int) DB::table('organizations')->insertGetId([
            'name' => 'Acme',
            'slug' => 'acme-'.uniqid('', true),
            'timezone' => 'UTC',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(): int
    {
        $organizationId = $this->organization();

        DB::table('repositories')->insertGetId([
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

        return $organizationId;
    }

    private function member(int $organizationId, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid('', true).'@example.com',
            'normalized_email' => $role.'-'.uniqid('', true).'@example.com',
            'password' => Hash::make('release-lens-2026'),
            'timezone' => 'UTC',
        ]);

        DB::table('organization_members')->insert([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
