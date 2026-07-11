<?php

namespace Tests\Feature;

use App\Modules\Notifications\Contracts\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_read_lifecycle(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $repository = app(NotificationRepositoryInterface::class);

        $notification = $repository->create(
            $organizationId,
            $userId,
            'release.released',
            'Release shipped',
            null,
            'release',
            1,
            null,
        );

        $this->assertSame(1, $repository->unreadCount($organizationId, $userId));

        $repository->markRead($organizationId, $userId, $notification->id);

        $this->assertSame(0, $repository->unreadCount($organizationId, $userId));
        $this->assertCount(1, $repository->forUser($organizationId, $userId));
        $this->assertCount(0, $repository->forUser($organizationId, $userId, unreadOnly: true));
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $repository = app(NotificationRepositoryInterface::class);

        $repository->create($organizationId, $userId, 'release.released', 'A', null, null, null, null);
        $repository->create($organizationId, $userId, 'release.released', 'B', null, null, null, null);

        $repository->markAllRead($organizationId, $userId);

        $this->assertSame(0, $repository->unreadCount($organizationId, $userId));
    }

    public function test_exists_within_window_respects_the_time_boundary(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $repository = app(NotificationRepositoryInterface::class);
        $repository->create($organizationId, $userId, 'deployment.failed', 'Failed', null, 'deployment', 1, 'dedup-key-1');

        $this->assertTrue($repository->existsWithinWindow($userId, 'dedup-key-1', 30));
        $this->assertFalse($repository->existsWithinWindow($userId, 'dedup-key-missing', 30));
    }

    public function test_preference_defaults_to_enabled_when_no_row_exists(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $preferences = app(NotificationPreferenceRepositoryInterface::class);

        $this->assertTrue($preferences->isEnabled($organizationId, $userId, 'release.released'));

        $preferences->setEnabled($organizationId, $userId, 'release.released', false);

        $this->assertFalse($preferences->isEnabled($organizationId, $userId, 'release.released'));
    }

    public function test_service_deduplicates_within_the_rule_window(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        $service = app(NotificationService::class);

        $service->notifyUsers($organizationId, [$userId], 'deployment.failed', 'Deploy failed', subjectType: 'deployment', subjectId: 42);
        $service->notifyUsers($organizationId, [$userId], 'deployment.failed', 'Deploy failed again', subjectType: 'deployment', subjectId: 42);

        $this->assertSame(
            1,
            DB::table('notifications')->where('user_id', $userId)->where('type', 'deployment.failed')->count(),
        );
    }

    public function test_service_skips_users_who_disabled_the_notification_type(): void
    {
        $organizationId = $this->organization();
        $userId = $this->user();
        app(NotificationPreferenceRepositoryInterface::class)->setEnabled($organizationId, $userId, 'release.released', false);

        app(NotificationService::class)->notifyUsers($organizationId, [$userId], 'release.released', 'Released');

        $this->assertSame(0, DB::table('notifications')->where('user_id', $userId)->count());
    }

    public function test_service_rejects_an_unknown_notification_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(NotificationService::class)->notifyUsers(1, [1], 'not.a.real.type', 'Title');
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

    private function user(): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'user-'.uniqid('', true).'@example.com',
            'normalized_email' => 'user-'.uniqid('', true).'@example.com',
            'password' => bcrypt('release-lens-2026'),
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
