<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\ListChatterAgentAssignmentAction;

/**
 * The attached/available split behind the Agents tab of a chatter's detail
 * sheet: protected agents the viewer may manage only, the chatter's grants in
 * `attached`, and nothing from another workspace.
 */
it('splits the workspace protected agents into attached and available', function (): void {
    $owner = User::factory()->create();
    $workspaceId = $owner->workspace()->id;

    $granted = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => false, 'name' => 'Billing']);
    $other = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => false, 'name' => 'Support']);
    $public = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => true, 'name' => 'Open']);

    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $chatter->agents()->attach($granted->id, ['workspace_id' => $workspaceId]);

    $result = app(ListChatterAgentAssignmentAction::class)->execute($chatter, $owner);

    expect($result->attached->toCollection()->pluck('id')->all())->toBe([$granted->external_id])
        ->and($result->available->toCollection()->pluck('id')->all())->toBe([$other->external_id])
        ->and($result->available->toCollection()->pluck('id')->all())->not->toContain($public->external_id);
});

it('leaves another workspace\'s agents out entirely', function (): void {
    $owner = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();

    Agent::factory()->forOwner(User::factory()->create())->create(['is_public' => false]);

    $result = app(ListChatterAgentAssignmentAction::class)->execute($chatter, $owner);

    expect($result->attached->count())->toBe(0)
        ->and($result->available->count())->toBe(0);
});

it('leaves out the agents the viewing teammate\'s role does not reach', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);
    $reached = Agent::factory()->create(['workspace_id' => $owner->workspace()->id, 'team_id' => $team->id, 'is_public' => false, 'name' => 'Billing']);
    Agent::factory()->create(['workspace_id' => $owner->workspace()->id, 'team_id' => $team->id, 'is_public' => false, 'name' => 'Support']);
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $teammate = teammateWithPermissions($owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$reached->external_id]);

    $result = app(ListChatterAgentAssignmentAction::class)->execute($chatter, $teammate);

    expect($result->available->toCollection()->pluck('id')->all())->toBe([$reached->external_id]);
});
