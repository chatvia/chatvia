<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
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
 * Plus the two happy paths: create-on-first-accept, and attach-existing.
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

    $team = app(TeamService::class)->createDefaultTeam($owner);

    $role = teamRole($team);

    /** @var Invitation $invitation */
    $invitation = $team->invitations()->create([
        'email' => $invitedEmail,
        'role_id' => $role->id,
    ]);

    return [$team, $invitation, $owner, $role];
}

it('creates the account on first accept and attaches the team_user membership row', function (): void {
    Event::fake([Registered::class]);

    [$team, $invitation, , $role] = atiTeamAndInvitation();

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'a-real-password-1234',
        name: 'New Teammate',
    ));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('invitee@example.com')
        ->and($user->name)->toBe('New Teammate')
        ->and($user->workspaceRole())->toBe(WorkspaceRole::Member)
        ->and($user->workspace()->is($team->workspace))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-real-password-1234', $user->password))->toBeTrue();

    // The membership pivot is the load-bearing grant.
    expect(DB::table('team_user')
        ->where('team_id', $team->id)
        ->where('user_id', $user->id)
        ->where('role_id', $role->id)
        ->exists())->toBeTrue();

    // Invitation is consumed.
    expect(Invitation::whereKey($invitation->id)->exists())->toBeFalse();

    Event::assertDispatched(Registered::class);
});

it('attaches an existing workspace user when the password matches, without recreating the account', function (): void {
    Event::fake([Registered::class]);

    [$team, $invitation, $owner, $role] = atiTeamAndInvitation('existing@example.com');

    $existing = User::factory()->employee($owner)->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'correct-horse-battery',
        name: 'Ignored When Existing',
    ));

    expect($user->id)->toBe($existing->id)
        ->and(DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', $existing->id)
            ->where('role_id', $role->id)
            ->exists())->toBeTrue()
        ->and(Invitation::whereKey($invitation->id)->exists())->toBeFalse();

    // No new account was registered for an already-existing user.
    Event::assertNotDispatched(Registered::class);
});

it('rejects an invitation older than 7 days', function (): void {
    [, $invitation] = atiTeamAndInvitation();

    // Backdate past the 7-day window (timestamps are auto-set on create).
    $invitation->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'a-real-password-1234',
        name: 'Too Late',
    )))->toThrow(DomainValidationException::class, 'This invitation has expired.');
});

it('creates a separate staff account when a chatter of the workspace has the invited email', function (): void {
    Event::fake([Registered::class]);

    [$team, $invitation, $owner, $role] = atiTeamAndInvitation('chatter@example.com');

    // An end-user of this workspace that happens to share the invited email.
    $chatter = Chatter::factory()->ofWorkspace($owner)->create([
        'email' => 'chatter@example.com',
        'password' => Hash::make('chatter-password'),
    ]);

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'a-new-staff-password',
        name: 'Teammate',
    ));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->workspaceRole())->toBe(WorkspaceRole::Member)
        ->and($user->workspace()->is($owner->workspace()))->toBeTrue()
        ->and(Hash::check('a-new-staff-password', $user->password))->toBeTrue()
        ->and(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $user->id)->where('role_id', $role->id)->exists())->toBeTrue();

    // The chatter is untouched: same row, same password, still a chatter.
    $chatter->refresh();
    expect(Hash::check('chatter-password', $chatter->password))->toBeTrue()
        ->and($chatter->workspace_id)->toBe($owner->workspace()->id);

    Event::assertDispatched(Registered::class);
});

it('rejects a wrong password with the same message, whoever holds the email', function (string $holder): void {
    [, $invitation, $owner] = atiTeamAndInvitation('existing@example.com');

    $attributes = ['email' => 'existing@example.com', 'password' => Hash::make('the-correct-password')];
    $holder === 'teammate'
        ? User::factory()->employee($owner)->create($attributes)
        : User::factory()->create($attributes);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'the-WRONG-password',
        name: 'Imposter',
    )))->toThrow(DomainValidationException::class, __('The provided password is incorrect.'));

    expect(Invitation::whereKey($invitation->id)->exists())->toBeTrue();
})->with([
    'a teammate of this workspace' => 'teammate',
    // Never says that the account is in another workspace before the password is proven.
    'the owner of another workspace' => 'owner',
]);

it('refuses an account that belongs to another workspace, even with the right password', function (string $kind): void {
    [$team, $invitation] = atiTeamAndInvitation('elsewhere@example.com');

    $attrs = ['email' => 'elsewhere@example.com', 'password' => Hash::make('correct-horse-battery')];
    $existing = $kind === 'owner'
        ? User::factory()->create($attrs)
        : User::factory()->employee(User::factory()->create())->create($attrs);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'correct-horse-battery',
    )))->toThrow(DomainValidationException::class);

    expect(DB::table('team_user')->where('team_id', $team->id)->where('user_id', $existing->id)->exists())->toBeFalse()
        ->and(Invitation::whereKey($invitation->id)->exists())->toBeTrue();
})->with(['owner of another workspace' => ['owner'], 'teammate of another workspace' => ['employee']]);

it('refuses a deactivated teammate', function (): void {
    [$team, $invitation, $owner] = atiTeamAndInvitation('sleepy@example.com');

    User::factory()->employee($owner)->create([
        'email' => 'sleepy@example.com',
        'password' => Hash::make('correct-horse-battery'),
        'deactivated_at' => now(),
    ]);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'correct-horse-battery',
    )))->toThrow(DomainValidationException::class);
});

it('matches the invited email without case', function (): void {
    [$team, $invitation, $owner] = atiTeamAndInvitation('Mixed.Case@Example.com');

    $existing = User::factory()->employee($owner)->create([
        'email' => 'mixed.case@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $user = app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'correct-horse-battery',
    ));

    expect($user->id)->toBe($existing->id);
});
