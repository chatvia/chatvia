<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateDefaultRolesAction;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;

/**
 * A workspace's default team: made with its roles, and never left without
 * them when making the roles fails.
 */
it('creates the default team together with its roles', function (): void {
    $user = User::factory()->create(['name' => 'Dana']);

    $team = app(TeamService::class)->createDefaultTeam($user);

    expect($team->workspace_id)->toBe($user->workspace()->id)
        ->and($team->name)->toBe("Dana's Team")
        ->and($team->roles()->count())->toBe(count(config('team-permissions.roles')));
});

it('leaves no role-less team behind when creating the roles fails', function (): void {
    $user = User::factory()->create();

    $this->mock(CreateDefaultRolesAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('roles failed'));

    expect(fn () => app(TeamService::class)->createDefaultTeam($user))->toThrow(RuntimeException::class, 'roles failed');

    expect(Team::query()->where('workspace_id', $user->workspace()->id)->exists())->toBeFalse();
});
