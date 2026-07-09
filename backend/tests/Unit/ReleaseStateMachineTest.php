<?php

namespace Tests\Unit;

use App\Modules\Releases\Enums\ReleaseState;
use App\Modules\Releases\Support\ReleaseStateMachine;
use PHPUnit\Framework\TestCase;

class ReleaseStateMachineTest extends TestCase
{
    public function test_draft_can_move_to_in_review_or_cancelled_only(): void
    {
        $this->assertTrue(ReleaseStateMachine::canTransition(ReleaseState::Draft, ReleaseState::InReview));
        $this->assertTrue(ReleaseStateMachine::canTransition(ReleaseState::Draft, ReleaseState::Cancelled));
        $this->assertFalse(ReleaseStateMachine::canTransition(ReleaseState::Draft, ReleaseState::Approved));
        $this->assertFalse(ReleaseStateMachine::canTransition(ReleaseState::Draft, ReleaseState::Released));
        $this->assertFalse(ReleaseStateMachine::canTransition(ReleaseState::Draft, ReleaseState::Closed));
    }

    public function test_approved_can_move_back_to_in_review_for_reapproval(): void
    {
        $this->assertTrue(ReleaseStateMachine::canTransition(ReleaseState::Approved, ReleaseState::InReview));
        $this->assertTrue(ReleaseStateMachine::canTransition(ReleaseState::Approved, ReleaseState::Released));
    }

    public function test_released_can_only_move_to_closed(): void
    {
        $this->assertSame([ReleaseState::Closed], ReleaseStateMachine::allowedTransitions(ReleaseState::Released));
    }

    public function test_closed_and_cancelled_are_terminal(): void
    {
        $this->assertSame([], ReleaseStateMachine::allowedTransitions(ReleaseState::Closed));
        $this->assertSame([], ReleaseStateMachine::allowedTransitions(ReleaseState::Cancelled));
    }
}
