<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\Event;
use Support\Exceptions\DomainValidationException;

/**
 * InviteTeamMemberAction attaches a teammate of the inviter's workspace
 * straight away under the chosen role. Anyone else (the owner, or an account
 * of another workspace) is refused, because the form request only checks that
 * the id is in some workspace. The email path hands over to
 * CreateTeamInvitationAction, which has its own test.
 */
beforeEach(function (): void {
    Event::fake([TeamInvitationCreated::class]);

    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);

    // Default teams only carry the `owner` role, which is never invitable.
    teamRole($this->team);
});

function itmAddUser(Team $team, User $inviter, User $user): void
{
    app(InviteTeamMemberAction::class)->execute(new InviteTeamMemberData(
        team: $team,
        inviter: $inviter,
        role: 'support',
        userId: $user->id,
    ));
}

it('attaches a teammate of the workspace under the role, without an invitation', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();

    itmAddUser($this->team, $this->owner, $teammate);

    $member = $this->team->members()->whereKey($teammate->id)->sole();

    expect($member->pivot->role_id)->toBe($this->team->roles()->where('code', 'support')->value('id'))
        ->and($this->team->invitations()->exists())->toBeFalse();

    Event::assertNotDispatched(TeamInvitationCreated::class);
});

it('refuses to add someone who is not a teammate of the inviter workspace', function (string $who): void {
    $user = match ($who) {
        'the owner' => $this->owner,
        'another workspace teammate' => User::factory()->employee()->create(),
    };

    expect(fn () => itmAddUser($this->team, $this->owner, $user))
        ->toThrow(DomainValidationException::class, 'You can only add users you have invited.');

    expect($this->team->members()->whereKey($user->id)->exists())->toBeFalse();
})->with(['the owner', 'another workspace teammate']);
