<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * Regression: has_permission_to_agent() gave a team owner every permission on
 * ANY agent while they had a current team — including another workspace's
 * agent. The owner shortcut now covers their own workspace's agents only.
 */
beforeEach(function (): void {
    $this->ownerA = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->ownerA);
    $this->agentA = createAgent($this->ownerA);

    $ownerB = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($ownerB);
    $this->agentB = createAgent($ownerB);
});

it('grants a team owner every permission on their own agent', function (): void {
    $this->actingAs($this->ownerA);

    expect(has_permission_to_agent($this->agentA, 'agent.setup'))->toBeTrue()
        ->and(has_permission_to_agent($this->agentA, 'agent.delete'))->toBeTrue();
});

it('denies a team owner any permission on another workspace agent', function (): void {
    $this->actingAs($this->ownerA);

    expect(has_permission_to_agent($this->agentB, 'agent.setup'))->toBeFalse()
        ->and(has_permission_to_agent($this->agentB, 'agent.chat.view'))->toBeFalse();
});
