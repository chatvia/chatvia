<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;

/**
 * Reactivating a workspace switches its members back on, except a member who
 * was switched off on their own before the workspace was.
 */
it('switches the members back on, except one switched off on their own before the workspace was', function (): void {
    $workspace = User::factory()->create();
    $member = User::factory()->employee($workspace)->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $bannedChatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $bannedTeammate = User::factory()->employee($workspace)->create();
    $this->travelTo(now()->subWeek());
    app(DeactivateAccountAction::class)->execute($bannedChatter, 'individual ban');
    app(DeactivateAccountAction::class)->execute($bannedTeammate, 'left the company');
    $this->travelBack();
    app(DeactivateAccountAction::class)->execute($workspace, 'temporary');

    app(ReactivateAccountAction::class)->execute($workspace->fresh());

    expect($workspace->fresh()->isDeactivated())->toBeFalse()
        ->and($member->fresh())->isDeactivated()->toBeFalse()->deactivation_reason->toBeNull()
        ->and($chatter->fresh())->isDeactivated()->toBeFalse()->deactivation_reason->toBeNull()
        ->and($bannedChatter->fresh())->isDeactivated()->toBeTrue()->deactivation_reason->toBe('individual ban')
        ->and($bannedTeammate->fresh())->isDeactivated()->toBeTrue()->deactivation_reason->toBe('left the company');
});
