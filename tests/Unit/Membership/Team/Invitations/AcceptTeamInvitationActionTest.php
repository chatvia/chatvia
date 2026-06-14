<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\User\UserType;
use Support\Exceptions\DomainValidationException;

/**
 * AcceptTeamInvitationAction is the access-grant step that turns a pending
 * team_invitations row into a real team_user membership (creating the user
 * account on first accept). It is security-critical: every guard below is a
 * gate that, if it regressed, would let an invite link grant access it
 * shouldn't.
 *  - Expiry: a stale (>7d) link is dead.
 *  - Owner subscription: a lapsed owner can't onboard new seats.
 *  - Owner seat-limit: the shared workspace-users cap is enforced.
 *  - Chatter takeover: an end-user (chat) account can't be upgraded into a
 *    dashboard team member via the invite.
 *  - Password: an existing account must prove ownership before being attached.
 * Plus the two happy paths: create-on-first-accept, and attach-existing.
 */

/**
 * Provision a team owner that clears the subscription + seat gates, returning
 * the team plus a fresh invitation into a non-owner role.
 *
 * @return array{0: Team, 1: Invitation, 2: User, 3: Role}
 */
function atiTeamAndInvitation(string $invitedEmail = 'invitee@example.com'): array
{
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    // hasActiveSubscription() gates the accept — seed an active subscription
    // the way SubscribeUserAction / the existing invite test does.
    $owner->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_'.Str::random(6),
        'quantity' => 1,
    ]);
    $owner->refresh();

    $team = app(TeamService::class)->createDefaultTeam($owner);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

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
        ->and($user->type)->toBe(UserType::Employee)
        ->and($user->workspace_id)->toBe($team->owner->id)
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

it('rejects accept when the team owner has no active subscription', function (): void {
    $owner = User::factory()->create(['email' => 'broke-owner@example.com']);
    $team = app(TeamService::class)->createDefaultTeam($owner);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    $invitation = $team->invitations()->create([
        'email' => 'invitee@example.com',
        'role_id' => $role->id,
    ]);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation,
        password: 'a-real-password-1234',
        name: 'No Sub',
    )))->toThrow(DomainValidationException::class, 'The team owner does not have an active subscription.');
});

it('rejects accept when the team owner has reached their workspace user limit', function (): void {
    [, $invitation, $owner] = atiTeamAndInvitation();

    // Active subscription, but zero seats — canAddWorkspaceUser() returns false
    // because workspaceUsersCount() (0) is not < totalWorkspaceUsersLimit() (0).
    $owner->quota()->create([
        'workspace_users_limit' => 0,
        'extra_members_limit' => 0,
    ]);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'a-real-password-1234',
        name: 'No Seats',
    )))->toThrow(DomainValidationException::class, 'The team owner has reached their workspace user limit. Please contact them to upgrade their plan.');
});

it('rejects a chat-user (chatter) account from joining a team', function (): void {
    [, $invitation, $owner] = atiTeamAndInvitation('chatter@example.com');

    // An end-user account that happens to share the invited email.
    User::factory()->chatter($owner)->create([
        'email' => 'chatter@example.com',
        'password' => Hash::make('whatever-password'),
    ]);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'whatever-password',
        name: 'Chatter',
    )))->toThrow(DomainValidationException::class, 'This account is a chat user and cannot join a team.');
});

it('rejects accept when the existing account password is incorrect', function (): void {
    [, $invitation, $owner] = atiTeamAndInvitation('existing@example.com');

    User::factory()->employee($owner)->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('the-correct-password'),
    ]);

    expect(fn () => app(AcceptTeamInvitationAction::class)->execute(new AcceptInvitationData(
        invitation: $invitation->fresh(),
        password: 'the-WRONG-password',
        name: 'Imposter',
    )))->toThrow(DomainValidationException::class, 'The provided password is incorrect.');
});
