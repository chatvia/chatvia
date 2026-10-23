<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Inbox\InboxAgents;
use Domain\Membership\Team\Services\TeamService;

/**
 * Which agents' chats a staff member sees in the Inbox and where they may
 * reply: the Chat Log's own view and takeover permissions, plus the agent's
 * handoff switch.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();
    $this->support = makeTeamAgent($this->owner, 'Support');
    $this->sales = makeTeamAgent($this->owner, 'Sales');
    $this->inboxAgents = app(InboxAgents::class);
});

it('gives the owner every agent and lets them reply', function (): void {
    expect($this->inboxAgents->viewableBy($this->owner)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->support->id, $this->sales->id])->sort()->values()->all())
        ->and($this->inboxAgents->canReplyOn($this->owner, $this->support))->toBeTrue();
});

it('gives a teammate only the agents their role may view', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view'], allAgents: false, agentExternalIds: [$this->sales->external_id]);

    expect($this->inboxAgents->viewableBy($teammate)->pluck('id')->all())->toBe([$this->sales->id])
        ->and($this->inboxAgents->canReplyOn($teammate, $this->sales))->toBeFalse();
});

it('gives a teammate without the view permission no agents', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.setup']);

    expect($this->inboxAgents->viewableBy($teammate))->toBeEmpty();
});

it('does not let anyone reply when the agent has handoff off', function (): void {
    $this->support->setSetting('handoff_enabled', false, 'widget');

    expect($this->inboxAgents->canReplyOn($this->owner, $this->support->fresh()))->toBeFalse();
});
