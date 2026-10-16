<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\ListChatterAgentAssignmentAction;

/**
 * The attached/available split behind the Agents tab of a chatter's detail
 * sheet: protected agents only, the chatter's grants in `attached`, and
 * nothing from another workspace.
 */
it('splits the workspace protected agents into attached and available', function (): void {
    $owner = User::factory()->create();
    $workspaceId = $owner->workspace()->id;

    $granted = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => false, 'name' => 'Billing']);
    $other = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => false, 'name' => 'Support']);
    $public = Agent::factory()->create(['workspace_id' => $workspaceId, 'is_public' => true, 'name' => 'Open']);

    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $chatter->agents()->attach($granted->id, ['workspace_id' => $workspaceId]);

    $result = app(ListChatterAgentAssignmentAction::class)->execute($chatter);

    expect($result->attached->toCollection()->pluck('id')->all())->toBe([$granted->external_id])
        ->and($result->available->toCollection()->pluck('id')->all())->toBe([$other->external_id])
        ->and($result->available->toCollection()->pluck('id')->all())->not->toContain($public->external_id);
});

it('leaves another workspace\'s agents out entirely', function (): void {
    $owner = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();

    Agent::factory()->forOwner(User::factory()->create())->create(['is_public' => false]);

    $result = app(ListChatterAgentAssignmentAction::class)->execute($chatter);

    expect($result->attached->count())->toBe(0)
        ->and($result->available->count())->toBe(0);
});
