<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\AssignUserToRoleAction;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;

it('attaches a new member and moves an existing one to the new role', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $roleFor = fn (string $name): Role => teamRole($team, $name, permissions: []);
    $support = $roleFor('Support');
    $sales = $roleFor('Sales');
    $member = User::factory()->create();

    app(AssignUserToRoleAction::class)->execute($team, $support, $member);
    expect($team->members()->whereKey($member->id)->first()?->pivot->role_id)->toBe($support->id);

    app(AssignUserToRoleAction::class)->execute($team, $sales, $member);
    expect($team->members()->where('users.id', $member->id)->count())->toBe(1)
        ->and($team->members()->whereKey($member->id)->first()?->pivot->role_id)->toBe($sales->id);
});
