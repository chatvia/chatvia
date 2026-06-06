<?php

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\DB;

it('hard-deletes the user row', function (): void {
    $user = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

it('does not touch unrelated users', function (): void {
    $target = User::factory()->create();
    $bystander = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($target);

    expect(User::whereKey($bystander->id)->exists())->toBeTrue();
});

it('tears down team memberships, owned teams, and workspace sub-users', function (): void {
    $owner = User::factory()->create();
    $team = $owner->ownedTeams()->firstOrFail();
    $role = $team->roles()->firstOrFail();

    // The owner is a member of their own team — this team_user row is exactly
    // what the team_user.team_id RESTRICT FK trips on if not cleared first.
    $team->members()->attach($owner->id, ['role_id' => $role->id]);

    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);

    $chatter = User::factory()->chatter($owner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(User::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Team::whereKey($team->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherEmployee = User::factory()->employee($otherOwner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::whereKey($otherEmployee->id)->exists())->toBeTrue();
});
