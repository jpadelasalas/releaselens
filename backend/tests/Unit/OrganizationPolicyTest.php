<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class OrganizationPolicyTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_owner_can_perform_every_workspace_action(): void
    {
        $policy = $this->policyForRole('owner');
        $user = $this->user();

        $this->assertTrue($policy->view($user, 10)->allowed());
        $this->assertTrue($policy->manageMembers($user, 10)->allowed());
        $this->assertTrue($policy->manageGitHub($user, 10)->allowed());
        $this->assertTrue($policy->disconnectGitHub($user, 10)->allowed());
        $this->assertTrue($policy->manageRepositories($user, 10)->allowed());
        $this->assertTrue($policy->requestSynchronization($user, 10)->allowed());
    }

    public function test_manager_and_viewer_permissions_follow_the_role_matrix(): void
    {
        $managerPolicy = $this->policyForRole('manager');
        $viewerPolicy = $this->policyForRole('viewer');
        $user = $this->user();

        $this->assertTrue($managerPolicy->view($user, 10)->allowed());
        $this->assertTrue($managerPolicy->manageGitHub($user, 10)->allowed());
        $this->assertTrue($managerPolicy->manageRepositories($user, 10)->allowed());
        $this->assertTrue($managerPolicy->requestSynchronization($user, 10)->allowed());
        $this->assertFalse($managerPolicy->manageMembers($user, 10)->allowed());
        $this->assertFalse($managerPolicy->disconnectGitHub($user, 10)->allowed());

        $this->assertTrue($viewerPolicy->view($user, 10)->allowed());
        $this->assertFalse($viewerPolicy->manageGitHub($user, 10)->allowed());
        $this->assertFalse($viewerPolicy->manageRepositories($user, 10)->allowed());
        $this->assertFalse($viewerPolicy->requestSynchronization($user, 10)->allowed());
    }

    public function test_non_member_workspace_is_hidden_as_not_found(): void
    {
        $organizations = Mockery::mock(OrganizationWorkspaceRepositoryInterface::class);
        $organizations->shouldReceive('membershipForUser')
            ->once()
            ->with(10, 5)
            ->andReturnNull();

        $response = (new OrganizationPolicy($organizations))
            ->manageMembers($this->user(), 10);

        $this->assertFalse($response->allowed());
        $this->assertSame(404, $response->status());
    }

    private function policyForRole(string $role): OrganizationPolicy
    {
        $organizations = Mockery::mock(OrganizationWorkspaceRepositoryInterface::class);
        $organizations->shouldReceive('membershipForUser')
            ->with(10, 5)
            ->andReturn((object) ['role' => $role]);

        return new OrganizationPolicy($organizations);
    }

    private function user(): User
    {
        $user = new User;
        $user->id = 5;

        return $user;
    }
}
