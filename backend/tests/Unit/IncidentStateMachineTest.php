<?php

namespace Tests\Unit;

use App\Modules\Incidents\Enums\IncidentState;
use App\Modules\Incidents\Support\IncidentStateMachine;
use PHPUnit\Framework\TestCase;

class IncidentStateMachineTest extends TestCase
{
    public function test_investigating_can_only_move_to_identified(): void
    {
        $this->assertTrue(IncidentStateMachine::canTransition(IncidentState::Investigating, IncidentState::Identified));
        $this->assertFalse(IncidentStateMachine::canTransition(IncidentState::Investigating, IncidentState::Monitoring));
        $this->assertFalse(IncidentStateMachine::canTransition(IncidentState::Investigating, IncidentState::Resolved));
        $this->assertFalse(IncidentStateMachine::canTransition(IncidentState::Investigating, IncidentState::Closed));
    }

    public function test_resolved_can_reopen_to_monitoring_or_close(): void
    {
        $this->assertTrue(IncidentStateMachine::canTransition(IncidentState::Resolved, IncidentState::Monitoring));
        $this->assertTrue(IncidentStateMachine::canTransition(IncidentState::Resolved, IncidentState::Closed));
    }

    public function test_closed_is_terminal(): void
    {
        $this->assertSame([], IncidentStateMachine::allowedTransitions(IncidentState::Closed));
    }
}
