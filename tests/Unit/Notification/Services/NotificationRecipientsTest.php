<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Support\Facades\DB;

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

it('reaches a restricted teammate whose role lists the agent', function (): void {
    $listed = teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $recipients = app(NotificationRecipients::class)->forAgent($this->agent, 'agent.leads.view');

    expect(recipientIds($recipients))->toBe(recipientIds([$this->owner, $listed]));
});

it('reads a teammate without a current team in their oldest team, as the dashboard does', function (): void {
    $older = ownerTeam($this->owner);
    $newer = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Newer'));
    $member = teammateWithPermissions($this->owner, ['account.prompts'], team: $older);
    $newerRole = teamRole($newer, 'Security', permissions: ['account.security']);
    $newer->members()->attach($member->id, ['role_id' => $newerRole->id]);
    $member->forceFill(['current_team_id' => null])->save();

    expect(recipientIds(app(NotificationRecipients::class)->forWorkspace($this->agent->workspace_id, 'account.security')))->toBe([$this->owner->id])
        ->and(recipientIds(app(NotificationRecipients::class)->forWorkspace($this->agent->workspace_id, 'account.prompts')))->toBe(recipientIds([$this->owner, $member]));
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

        expect(recipientIds(app(NotificationRecipients::class)->forAgent($agent, 'agent.leads.view')))->toBe(recipientIds($expected));
    }

    $expected = $members->filter(fn (User $user): bool => $policy->allows($user, 'account.security'));

    expect(recipientIds(app(NotificationRecipients::class)->forWorkspace($this->agent->workspace_id, 'account.security')))->toBe(recipientIds($expected));
});

it('asks the same few queries however many teammates there are', function (): void {
    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        app(NotificationRecipients::class)->forAgent($this->agent, 'agent.leads.view');

        return $queries;
    };

    teammateWithPermissions($this->owner, ['agent.leads.view']);
    $few = $count();

    foreach (range(1, 4) as $ignored) {
        teammateWithPermissions($this->owner, ['agent.leads.view'], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    }

    expect($count())->toBe($few)->toBeLessThanOrEqual(5);
});
