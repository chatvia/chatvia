<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * AcceptTeamInvitationAction is the access-grant step that turns a pending
 * team_invitations row into a real team_user membership (creating the user
 * account on first accept). It is security-critical: every guard below is a
 * gate that, if it regressed, would let an invite link grant access it
 * shouldn't.
 *  - Expiry: a stale (>7d) link is dead.
 *  - Password: an existing account must prove ownership before being attached.
 *  - Tenancy: an account of another workspace, or a deactivated teammate, is
 *    refused even with the right password, and a wrong password gets the same
 *    message whoever holds the email.
 * Plus the two happy paths: create-on-first-accept, and attach-existing
 * (the email matched without case).
 * Chatters live in their own table, so a chatter with the invited email is a
 * different person: it never blocks the invite and is never turned into staff.
 */
/**
 * Provision a team owner, returning the team plus a fresh invitation into a
 * non-owner role.
 *
 * @return array{0: Team, 1: Invitation, 2: User, 3: Role}
 */
function atiTeamAndInvitation(string $invitedEmail = 'invitee@example.com'): array
{
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $team = app(CreateDefaultTeamAction::class)->execute($owner);

    $role = teamRole($team);

    /** @var Invitation $invitation */
    $invitation = $team->invitations()->create([
        'email' => $invitedEmail,
        'role_id' => $role->id,
    ]);

    return [$team, $invitation, $owner, $role];
}

it('creates the account on first accept, apart from a chatter with the same email, and consumes the invitation', function (): void {
    Event::fake([Registered::class]);
    [$team, $invitation, $owner, $role] = atiTeamAndInvitation('chatter@example.com');
    $chatter = Chatter::factory()->ofWorkspace($owner)->create([
        'email' => 'chatter@example.com',
        'password' => Hash::make('chatter-password'),
    ]);

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'a-real-password-1234',
        name: 'New Teammate',
    ));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('chatter@example.com')
        ->and($user->name)->toBe('New Teammate')
        ->and($user->workspaceRole())->toBe(WorkspaceRole::Member)
        ->and($user->workspace()->is($team->workspace))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-real-password-1234', $user->password))->toBeTrue()
        ->and(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $user->id)->where('role_id', $role->id)->exists())->toBeTrue()
        ->and(Invitation::whereKey($invitation->id)->exists())->toBeFalse()
        ->and(Hash::check('chatter-password', $chatter->fresh()->password))->toBeTrue()
        ->and($chatter->fresh()->workspace_id)->toBe($owner->workspace()->id);
    Event::assertDispatched(Registered::class);
});

it('attaches an existing teammate, matching the email without case, when the password matches', function (): void {
    Event::fake([Registered::class]);
    [$team, $invitation, $owner, $role] = atiTeamAndInvitation('Mixed.Case@Example.com');
    $existing = User::factory()->employee($owner)->create([
        'email' => 'mixed.case@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'correct-horse-battery',
        name: 'Ignored When Existing',
    ));

    expect($user->id)->toBe($existing->id)
        ->and(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $existing->id)->where('role_id', $role->id)->exists())->toBeTrue()
        ->and(Invitation::whereKey($invitation->id)->exists())->toBeFalse();
    Event::assertNotDispatched(Registered::class);
});

it('refuses an invitation link that must not grant access, and grants nothing', function (Closure $arrange, ?string $message): void {
    [$data, $unchanged] = $arrange();

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute($data))->toThrow(DomainValidationException::class, $message);

    $unchanged();
})->with([
    'an invitation older than 7 days' => [function (): array {
        [, $invitation] = atiTeamAndInvitation();
        $invitation->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

        return [new AcceptInvitationData(invitation: $invitation->fresh(), password: 'a-real-password-1234', name: 'Too Late'), fn () => expect(User::query()->where('email', 'invitee@example.com')->exists())->toBeFalse()];
    }, 'This invitation has expired.'],
    'a wrong password for a teammate' => [function (): array {
        [, $invitation, $owner] = atiTeamAndInvitation('existing@example.com');
        User::factory()->employee($owner)->create(['email' => 'existing@example.com', 'password' => Hash::make('the-correct-password')]);

        return [new AcceptInvitationData(invitation: $invitation->fresh(), password: 'the-WRONG-password', name: 'Imposter'), fn () => expect(Invitation::whereKey($invitation->id)->exists())->toBeTrue()];
    }, 'The provided password is incorrect.'],
    'a wrong password for the owner of another workspace, with the same message' => [function (): array {
        [, $invitation] = atiTeamAndInvitation('existing@example.com');
        User::factory()->create(['email' => 'existing@example.com', 'password' => Hash::make('the-correct-password')]);

        return [new AcceptInvitationData(invitation: $invitation->fresh(), password: 'the-WRONG-password', name: 'Imposter'), fn () => expect(Invitation::whereKey($invitation->id)->exists())->toBeTrue()];
    }, 'The provided password is incorrect.'],
    'the owner of another workspace with the right password' => [function (): array {
        [$team, $invitation] = atiTeamAndInvitation('elsewhere@example.com');
        $existing = User::factory()->create(['email' => 'elsewhere@example.com', 'password' => Hash::make('correct-horse-battery')]);

        return [new AcceptInvitationData(invitation: $invitation, password: 'correct-horse-battery'), fn () => expect(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $existing->id)->exists())->toBeFalse()
            ->and(Invitation::whereKey($invitation->id)->exists())->toBeTrue()];
    }, null],
    'a teammate of another workspace with the right password' => [function (): array {
        [$team, $invitation] = atiTeamAndInvitation('elsewhere@example.com');
        $existing = User::factory()->employee(User::factory()->create())->create(['email' => 'elsewhere@example.com', 'password' => Hash::make('correct-horse-battery')]);

        return [new AcceptInvitationData(invitation: $invitation, password: 'correct-horse-battery'), fn () => expect(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $existing->id)->exists())->toBeFalse()
            ->and(Invitation::whereKey($invitation->id)->exists())->toBeTrue()];
    }, null],
    'a deactivated teammate' => [function (): array {
        [$team, $invitation, $owner] = atiTeamAndInvitation('sleepy@example.com');
        $existing = User::factory()->employee($owner)->create(['email' => 'sleepy@example.com', 'password' => Hash::make('correct-horse-battery'), 'deactivated_at' => now()]);

        return [new AcceptInvitationData(invitation: $invitation, password: 'correct-horse-battery'), fn () => expect(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $existing->id)->exists())->toBeFalse()];
    }, null],
]);
