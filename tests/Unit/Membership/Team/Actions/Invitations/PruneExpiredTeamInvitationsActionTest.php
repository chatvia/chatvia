<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\PruneExpiredTeamInvitationsAction;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * A team invitation expires 7 days after it was (re)sent; it is deleted 30
 * days after that, so 37 days after `created_at`.
 */
function petiInvitation(Team $team, int $sentDaysAgo): Invitation
{
    $invitation = $team->invitations()->create([
        'role_id' => $team->roles()->firstOrFail()->id,
        'email' => Str::random(8).'@example.test',
    ]);
    $invitation->forceFill(['created_at' => now()->subDays($sentDaysAgo)])->save();

    return $invitation;
}

it('deletes invitations 30 days after they expired', function (): void {
    $team = app(TeamService::class)->createDefaultTeam(User::factory()->create());
    $old = [petiInvitation($team, 38)->id, petiInvitation($team, 100)->id];
    $kept = [petiInvitation($team, 36)->id, petiInvitation($team, 1)->id];

    $deleted = app(PruneExpiredTeamInvitationsAction::class)->execute(batchSize: 1);

    expect($deleted)->toBe(2)
        ->and(Invitation::query()->whereKey($old)->exists())->toBeFalse()
        ->and(Invitation::query()->whereKey($kept)->count())->toBe(2);
});
