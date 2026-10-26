<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamAllowance;

/**
 * TeamAllowance: without a license key a workspace may hold only the team it
 * starts with; with one there is no limit.
 */
it('allows the first team but no second one without a key', function (): void {
    config(['chatvia.license.licensed' => false]);
    $owner = User::factory()->create();
    $allowance = app(TeamAllowance::class);

    expect($allowance->canCreate($owner->workspace()))->toBeTrue();

    ownerTeam($owner);

    expect($allowance->canCreate($owner->workspace()))->toBeFalse();
});

it('allows any number of teams with a key', function (): void {
    config(['chatvia.license.licensed' => true]);
    $owner = User::factory()->create();
    ownerTeam($owner);

    expect(app(TeamAllowance::class)->canCreate($owner->workspace()))->toBeTrue();
});
