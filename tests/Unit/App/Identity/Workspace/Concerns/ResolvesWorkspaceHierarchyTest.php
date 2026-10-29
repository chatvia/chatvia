<?php

use App\Identity\Workspace\Models\User;

/**
 * Account-wide data (agents, sources, settings…) belongs to the workspace, and
 * every staff account resolves it through workspace(). So a workspace owner and
 * a teammate of that workspace see the SAME data, whether the relations are
 * loaded lazily or eagerly, and no other workspace's rows leak in.
 */
it('resolves the owner and a teammate to the same workspace data, lazily or eagerly, and no other workspace\'s', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $employee = User::factory()->employee($owner)->create();
    $stranger = User::factory()->create();
    $strangerAgent = createAgent($stranger);

    $users = User::query()
        ->whereKey([$owner->id, $employee->id, $stranger->id])
        ->with('workspaceMembership.workspace.agents')
        ->get()
        ->keyBy('id');

    expect($employee->workspace()->is($owner->workspace()))->toBeTrue()
        ->and($employee->workspace()->agents->pluck('id')->all())->toBe([$agent->id])
        ->and($users[$owner->id]->workspace()->agents->pluck('id')->all())->toBe([$agent->id])
        ->and($users[$employee->id]->workspace()->agents->pluck('id')->all())->toBe([$agent->id])
        ->and($users[$stranger->id]->workspace()->agents->pluck('id')->all())->toBe([$strangerAgent->id]);
});
