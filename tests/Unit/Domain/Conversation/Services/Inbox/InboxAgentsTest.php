<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Inbox\InboxAgents;

/**
 * Which agents' chats a staff member sees in the Inbox and where they may
 * reply: the Chat Log's own view and takeover permissions, plus the agent's
 * handoff switch and the install's license key.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->support = makeTeamAgent($this->owner, 'Support');
    $this->sales = makeTeamAgent($this->owner, 'Sales');
    $this->inboxAgents = app(InboxAgents::class);
});

it('gives a teammate only the agents their role may view, and none without the view permission', function (): void {
    $viewer = teammateWithPermissions($this->owner, ['agent.chat.view'], allAgents: false, agentExternalIds: [$this->sales->external_id]);
    $setupOnly = teammateWithPermissions($this->owner, ['agent.setup']);

    expect($this->inboxAgents->viewableBy($viewer)->pluck('id')->all())->toBe([$this->sales->id])
        ->and($this->inboxAgents->canReplyOn($viewer, $this->sales))->toBeFalse()
        ->and($this->inboxAgents->viewableBy($setupOnly))->toBeEmpty();
});

it('lets the owner reply only while the agent has handoff on and the install has a license key', function (): void {
    $this->support->setSetting('handoff_enabled', false, 'widget');

    $withKey = [$this->inboxAgents->canReplyOn($this->owner, $this->sales), $this->inboxAgents->canReplyOn($this->owner, $this->support->fresh())];
    withoutLicense();

    expect($withKey)->toBe([true, false])
        ->and($this->inboxAgents->viewableBy($this->owner))->toHaveCount(2)
        ->and($this->inboxAgents->canReplyOn($this->owner, $this->sales))->toBeFalse();
});
