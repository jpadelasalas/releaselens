<?php

namespace Tests\Unit;

use App\Modules\Webhooks\Support\WebhookEventAllowlist;
use PHPUnit\Framework\TestCase;

class WebhookEventAllowlistTest extends TestCase
{
    public function test_ping_is_supported_regardless_of_action(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertTrue($allowlist->supports('ping', null));
        $this->assertTrue($allowlist->supports('ping', 'anything'));
    }

    public function test_pull_request_supports_only_its_allowlisted_actions(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertTrue($allowlist->supports('pull_request', 'opened'));
        $this->assertTrue($allowlist->supports('pull_request', 'synchronize'));
        $this->assertTrue($allowlist->supports('pull_request', 'ready_for_review'));
        $this->assertFalse($allowlist->supports('pull_request', 'labeled'));
        $this->assertFalse($allowlist->supports('pull_request', null));
    }

    public function test_pull_request_review_supports_only_its_allowlisted_actions(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertTrue($allowlist->supports('pull_request_review', 'submitted'));
        $this->assertTrue($allowlist->supports('pull_request_review', 'dismissed'));
        $this->assertFalse($allowlist->supports('pull_request_review', 'requested'));
    }

    public function test_installation_and_installation_repositories_actions(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertTrue($allowlist->supports('installation', 'suspend'));
        $this->assertTrue($allowlist->supports('installation_repositories', 'added'));
        $this->assertFalse($allowlist->supports('installation', 'unknown_action'));
    }

    public function test_repository_actions(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertTrue($allowlist->supports('repository', 'renamed'));
        $this->assertTrue($allowlist->supports('repository', 'transferred'));
        $this->assertFalse($allowlist->supports('repository', 'privatized'));
    }

    public function test_unknown_events_are_not_supported(): void
    {
        $allowlist = new WebhookEventAllowlist;

        $this->assertFalse($allowlist->supports('deployment', 'created'));
        $this->assertFalse($allowlist->supports('workflow_run', null));
    }
}
