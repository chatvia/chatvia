<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Notification\Services\NotificationRecipients;

/**
 * Who a notification reaches: the owner and the workspace's active
 * teammates holding the permission (on the agent, for an agent's
 * notification), read the way the dashboard's permission policy reads them.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

/**
 * The ids of these people, sorted.
 *
 * @return list<int>
 */
function nrIds(iterable $users): array
{
    return collect($users)->pluck('id')->sort()->values()->all();
}

it('reaches the owner and the active teammates holding the permission on the agent, nobody else', function (): void {
    $otherAgent = createAgent($this->owner, ['name' => 'Other']);
    $holder = teammateWithPermissions($this->owner, ['agent.leads.view']);
    $listed = teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$otherAgent->external_id]);
    teammateWithPermissions($this->owner, ['agent.chat.view']);
    teammateWithPermissions($this->owner, ['agent.leads.view'])->update(['deactivated_at' => now()]);
    createAgent(User::factory()->create());

    $recipients = app(NotificationRecipients::class)->forAgent($this->agent, 'agent.leads.view');

    expect(nrIds($recipients))->toBe(nrIds([$this->owner, $holder, $listed]));
});

it('finds the holders of a workspace-wide permission, reading a teammate without a current team in their oldest team', function (): void {
    $security = teammateWithPermissions($this->owner, ['account.security']);
    $older = ownerTeam($this->owner);
    $newer = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Newer'));
    $member = teammateWithPermissions($this->owner, ['account.prompts'], team: $older);
    $newer->members()->attach($member->id, ['role_id' => teamRole($newer, 'Security', permissions: ['account.security'])->id]);
    $member->forceFill(['current_team_id' => null])->save();

    $recipients = app(NotificationRecipients::class);

    expect(nrIds($recipients->forWorkspace($this->agent->workspace_id, 'account.security')))->toBe(nrIds([$this->owner, $security]))
        ->and(nrIds($recipients->forWorkspace($this->agent->workspace_id, 'account.prompts')))->toBe(nrIds([$this->owner, $member]));
});

it('picks the same people as the dashboard permission policy', function (): void {
    $otherAgent = createAgent($this->owner, ['name' => 'Other']);
    teammateWithPermissions($this->owner, ['agent.leads.view', 'account.security']);
    teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$otherAgent->external_id]);
    teammateWithPermissions($this->owner, ['agent.chat.view']);

    $policy = app(TeamPermissionPolicy::class);
    $members = User::query()
        ->whereHas('workspaceMembership', fn ($query) => $query->where('workspace_id', $this->agent->workspace_id))
        ->get()
        ->withRelationshipAutoloading();

    foreach ([$this->agent, $otherAgent] as $agent) {
        $expected = $members->filter(fn (User $user): bool => $policy->allowsOnAgent($user, $agent, 'agent.leads.view'));

        expect(nrIds(app(NotificationRecipients::class)->forAgent($agent, 'agent.leads.view')))->toBe(nrIds($expected));
    }

    $expected = $members->filter(fn (User $user): bool => $policy->allows($user, 'account.security'));

    expect(nrIds(app(NotificationRecipients::class)->forWorkspace($this->agent->workspace_id, 'account.security')))->toBe(nrIds($expected));
});
