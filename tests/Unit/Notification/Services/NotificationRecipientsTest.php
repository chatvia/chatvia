<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Services\NotificationRecipients;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

/** @return list<int> */
function recipientIds(iterable $users): array
{
    return collect($users)->pluck('id')->sort()->values()->all();
}

it('reaches the owner and the teammates holding the permission on the agent', function (): void {
    $takesOver = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
    teammateWithPermissions($this->owner, ['agent.chat.view']);

    $recipients = app(NotificationRecipients::class)->forAgent($this->agent, 'agent.chat.takeover');

    expect(recipientIds($recipients))->toBe(recipientIds([$this->owner, $takesOver]));
});

it('leaves out a teammate whose role does not reach the agent', function (): void {
    $otherAgent = createAgent($this->owner, ['name' => 'Other']);
    teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$otherAgent->external_id]);

    $recipients = app(NotificationRecipients::class)->forAgent($this->agent, 'agent.leads.view');

    expect(recipientIds($recipients))->toBe([$this->owner->id]);
});

it('leaves out deactivated accounts and other workspaces', function (): void {
    $deactivated = teammateWithPermissions($this->owner, ['agent.leads.view']);
    $deactivated->update(['deactivated_at' => now()]);
    $stranger = User::factory()->create();
    createAgent($stranger);

    $recipients = app(NotificationRecipients::class)->forAgent($this->agent, 'agent.leads.view');

    expect(recipientIds($recipients))->toBe([$this->owner->id]);
});

it('finds the holders of a workspace-wide permission', function (): void {
    $security = teammateWithPermissions($this->owner, ['account.security']);
    teammateWithPermissions($this->owner, ['account.prompts']);

    $recipients = app(NotificationRecipients::class)->forWorkspace($this->agent->workspace_id, 'account.security');

    expect(recipientIds($recipients))->toBe(recipientIds([$this->owner, $security]));
});
