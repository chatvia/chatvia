<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\RelationManagers\TeammatesRelationManager;
use App\Identity\Workspace\Models\User;
use Livewire\Livewire;

/**
 * The Teammates tab of a workspace's page in the admin panel: the owner
 * first, then the teammates, each with their workspace role and their team
 * roles.
 */
it('lists the owner first, then teammates with their team roles', function (): void {
    $owner = User::factory()->create(['name' => 'Dana Owner']);
    $team = ownerTeam($owner);
    $teammate = User::factory()->employee($owner)->create(['name' => 'Aaron Teammate']);
    $team->members()->attach($teammate->id, ['role_id' => teamRole($team, 'Support', ['agent.view'])->id]);

    Livewire::actingAs(adminUser(), 'admin')
        ->test(TeammatesRelationManager::class, ['ownerRecord' => $owner->workspace(), 'pageClass' => ViewWorkspace::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$owner, $teammate], inOrder: true)
        ->assertTableColumnStateSet('workspace_role', 'Owner', $owner)
        ->assertTableColumnStateSet('workspace_role', 'Teammate', $teammate)
        ->assertTableColumnStateSet('teams', ["{$team->name} · Support"], $teammate)
        ->assertTableColumnStateSet('teams', ['Every team, as owner'], $owner);
});
