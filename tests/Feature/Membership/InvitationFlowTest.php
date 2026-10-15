<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Notifications\TeamInvitationNotification;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
 * HTTP-level coverage for the two invitation flows and their side effects:
 *
 *   teams.invite           — invitation row + TeamInvitationCreated, and (with
 *                            real events) the SendTeamInvitationEmail listener
 *                            registered in DomainServiceProvider mailing the
 *                            invitee on demand.
 *   agents.chatters.store   — InviteAgentChatterAction's row + chatter + pivot +
 *                            AgentChatterInvited, and the
 *                            SendAgentChatterInvitationEmail listener end to end;
 *                            an email known in another workspace becomes a
 *                            separate chatter and the answer never says so.
 *   teams.cancel-invitation — owner deletes the row; a teammate is sent back
 *                            to the dashboard; an invitation of another team
 *                            404s (scoped lookup).
 */

/**
 * A workspace owner with its default team.
 */
function invitationFlowOwner(): User
{
    $owner = User::factory()->create();

    app(TeamService::class)->createDefaultTeam($owner);

    return $owner;
}

/**
 * Default teams only carry the (never-invitable) `owner` role — invites
 * validate against `invitableRoles()`, so add a plain one to invite into.
 */
function invitationFlowSupportRole(Team $team): void
{
    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));
}

/**
 * The signed accept URL from an invitation email.
 */
function invitationFlowAcceptUrl(int $invitationId): string
{
    return URL::signedRoute('teams.invitation.accept', ['invitation' => $invitationId]);
}

beforeEach(function (): void {
    $this->owner = invitationFlowOwner();
    $this->team = $this->owner->primaryTeam();
});

describe('teams.invite', function (): void {
    beforeEach(function (): void {
        invitationFlowSupportRole($this->team);
    });

    it('answers another workspace\'s teammate id exactly like an unknown id', function (): void {
        $foreign = User::factory()->employee(User::factory()->create())->create();

        $errorFor = function (int $userId): string {
            $this->actingAs($this->owner)
                ->from('/')
                ->post(route('teams.invite', $this->team), ['user_id' => $userId, 'role' => 'support'])
                ->assertSessionHasErrors('user_id');

            return session('errors')->first('user_id');
        };

        expect($errorFor($foreign->id))->toBe($errorFor(999_999_999))
            ->and($this->team->members()->whereKey($foreign->id)->exists())->toBeFalse();
    });

    it('adds a teammate of the same workspace by id', function (): void {
        $teammate = User::factory()->employee($this->owner)->create();

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->team), ['user_id' => $teammate->id, 'role' => 'support'])
            ->assertSessionHasNoErrors();

        expect($this->team->members()->whereKey($teammate->id)->exists())->toBeTrue();
    });

    it('persists the invitation and dispatches TeamInvitationCreated', function (): void {
        Event::fake([TeamInvitationCreated::class]);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->team), [
                'email' => 'invitee@example.com',
                'role' => 'support',
            ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $invitation = $this->team->invitations()->where('email', 'invitee@example.com')->first();

        expect($invitation)->not->toBeNull()
            ->and($invitation->role->code)->toBe('support');

        Event::assertDispatched(
            TeamInvitationCreated::class,
            fn (TeamInvitationCreated $event): bool => $event->email === 'invitee@example.com'
                && $event->invitation->is($invitation),
        );
    });

    it('invites the email of a workspace chatter like any other email: a chatter is not a staff account', function (): void {
        Event::fake([TeamInvitationCreated::class]);
        Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'chatter@example.com']);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->team), [
                'email' => 'chatter@example.com',
                'role' => 'support',
            ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        expect($this->team->invitations()->where('email', 'chatter@example.com')->exists())->toBeTrue();
        Event::assertDispatched(TeamInvitationCreated::class);
    });

    it('emails the invitee through the registered listener', function (): void {
        // Events are NOT faked here: TeamInvitationCreated must actually reach
        // SendTeamInvitationEmail (wired in DomainServiceProvider — listeners
        // have no auto-discovery, so this pins the registration end to end).
        Notification::fake();

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->team), [
                'email' => 'invitee@example.com',
                'role' => 'support',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // The invitee has no User row yet, so the mail is routed on demand.
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'invitee@example.com',
        );
    });
});

describe('teams.invitation.show', function (): void {
    it('redirects a non-integer invitation id to login instead of 500ing', function (): void {
        // The id is a bigint; vulnerability scanners probe `/invitation/.env`.
        // Invitation::resolveRouteBinding() misses cleanly instead of sending
        // it to Postgres (a 22P02 error, a 500), and ->missing() redirects, all
        // before the `signed` middleware runs.
        $this->get('/invitation/.env')
            ->assertRedirect(route('login'));

        $this->get('/invitation/'.urlencode('.env'))
            ->assertRedirect(route('login'));
    });

    it('redirects a numeric-but-missing invitation id to login', function (): void {
        $this->get('/invitation/999999999')
            ->assertRedirect(route('login'));
    });
});

describe('agents.chatters.store', function (): void {
    beforeEach(function (): void {
        $this->agent = createAgent($this->owner);
    });

    it('provisions the chatter, persists the invitation and dispatches AgentChatterInvited', function (): void {
        Event::fake([AgentChatterInvited::class]);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('agents.chatters.store', $this->agent), [
                'email' => 'Chatter@Example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('invitation');

        // The email is normalised to lowercase before anything is persisted.
        $invitation = AgentChatterInvitation::query()
            ->where('agent_id', $this->agent->id)
            ->where('email', 'chatter@example.com')
            ->first();

        expect($invitation)->not->toBeNull()
            ->and($invitation->token)->not->toBeEmpty();

        // The action provisions the chatter account and the agent grant in the
        // same transaction — without the pivot row the accept link is useless.
        $chatter = $this->owner->workspace()->chatters()->where('email', 'chatter@example.com')->sole();

        expect($chatter)->toBeInstanceOf(Chatter::class)
            ->and($chatter->workspace_id)->toBe($this->owner->workspace()->id)
            ->and($chatter->email_verified_at)->toBeNull()
            ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
            ->and(User::query()->where('email', 'chatter@example.com')->exists())->toBeFalse();

        Event::assertDispatched(
            AgentChatterInvited::class,
            fn (AgentChatterInvited $event): bool => $event->agent->is($this->agent)
                && $event->email === 'chatter@example.com'
                && $event->token === $invitation->token,
        );
    });

    it('emails the invitee through the registered listener', function (): void {
        // Real events again — pins AgentChatterInvited → SendAgentChatterInvitationEmail.
        Notification::fake();

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('agents.chatters.store', $this->agent), [
                'email' => 'chatter@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(
            AgentChatterInvitationNotification::class,
            fn (AgentChatterInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'chatter@example.com',
        );
    });

    it('invites an email used in another workspace as a separate chatter, answering exactly as for a new email', function (): void {
        Event::fake([AgentChatterInvited::class]);

        $otherOwner = invitationFlowOwner();
        $foreign = Chatter::factory()->grantedTo(createAgent($otherOwner))->create(['email' => 'shared@example.com']);
        User::factory()->employee($otherOwner)->create(['email' => 'staff-elsewhere@example.com']);

        $flashFor = function (string $email): array {
            $this->actingAs($this->owner)
                ->from('/')
                ->post(route('agents.chatters.store', $this->agent), ['email' => $email])
                ->assertRedirect('/')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('invitation');

            return session('invitation');
        };

        $known = $flashFor('shared@example.com');
        $staff = $flashFor('staff-elsewhere@example.com');
        $unknown = $flashFor('brand-new@example.com');

        expect(array_keys($known))->toBe(array_keys($unknown))
            ->and(array_keys($staff))->toBe(array_keys($unknown))
            ->and($known['email'])->toBe('shared@example.com');

        // Two chatters with the same email, one per workspace.
        $local = $this->owner->workspace()->chatters()->where('email', 'shared@example.com')->sole();

        expect($local->id)->not->toBe($foreign->id)
            ->and($this->agent->chatters()->whereKey($local->id)->exists())->toBeTrue()
            ->and($this->agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
            ->and($this->owner->workspace()->chatters()->where('email', 'staff-elsewhere@example.com')->exists())->toBeTrue();
    });

    it('invites the email of a teammate of the same workspace as a chatter', function (): void {
        Event::fake([AgentChatterInvited::class]);
        $teammate = User::factory()->employee($this->owner)->create(['email' => 'teammate@example.com']);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('agents.chatters.store', $this->agent), ['email' => 'teammate@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $chatter = $this->owner->workspace()->chatters()->where('email', 'teammate@example.com')->sole();

        expect($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
            ->and($teammate->fresh()->isTeammate())->toBeTrue();
    });
});

describe('teams.cancel-invitation', function (): void {
    beforeEach(function (): void {
        invitationFlowSupportRole($this->team);

        $this->invitation = $this->team->invitations()->create([
            'email' => 'pending@example.com',
            'role_id' => $this->team->roles()->where('code', 'support')->value('id'),
        ]);
    });

    it('lets the owner cancel a pending invitation', function (): void {
        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        expect($this->team->invitations()->whereKey($this->invitation->id)->exists())->toBeFalse();
    });

    it('sends a teammate back to the dashboard and keeps the row', function (): void {
        $teammate = teammateWithPermissions($this->owner, ['team.invite'], $this->team);

        $this->actingAs($teammate)
            ->from('/')
            ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
            ->assertRedirect(route('dashboard'));

        expect($this->team->invitations()->whereKey($this->invitation->id)->exists())->toBeTrue();
    });

    it('hides the invitation from another workspace and keeps the row', function (): void {
        $outsider = invitationFlowOwner();

        $this->actingAs($outsider)
            ->from('/')
            ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
            ->assertNotFound();

        expect($this->team->invitations()->whereKey($this->invitation->id)->exists())->toBeTrue();
    });

    it('404s for an invitation that belongs to another team', function (): void {
        $other = invitationFlowOwner();
        invitationFlowSupportRole($other->primaryTeam());

        $foreign = $other->primaryTeam()->invitations()->create([
            'email' => 'foreign@example.com',
            'role_id' => $other->primaryTeam()->roles()->where('code', 'support')->value('id'),
        ]);

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.cancel-invitation', [$this->team, $foreign->id]))
            ->assertNotFound();

        expect($foreign->fresh())->not->toBeNull();
    });
});

describe('teams.invitation.accept', function (): void {
    beforeEach(function (): void {
        invitationFlowSupportRole($this->team);
        $this->role = $this->team->invitableRoles()->first();
    });

    it('sends an existing teammate with two-factor to the challenge instead of signing them in', function (): void {
        $teammate = User::factory()->employee($this->owner)->create([
            'email' => 'twofa@example.com',
            'password' => Hash::make('correct-horse-battery'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $invitation = $this->team->invitations()->create(['email' => 'twofa@example.com', 'role_id' => $this->role->id]);

        $this->post(invitationFlowAcceptUrl($invitation->id), ['password' => 'correct-horse-battery'])
            ->assertRedirect(route('two-factor.login'));

        expect(Auth::guard('web')->check())->toBeFalse()
            ->and(session('login.id'))->toBe($teammate->id);
    });

    it('signs a new teammate straight in', function (): void {
        $invitation = $this->team->invitations()->create(['email' => 'new@example.com', 'role_id' => $this->role->id]);

        $this->post(invitationFlowAcceptUrl($invitation->id), [
            'name' => 'New Person',
            'password' => 'a-strong-Password-123!',
            'password_confirmation' => 'a-strong-Password-123!',
            'policy' => '1',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        expect(Auth::guard('web')->user()?->email)->toBe('new@example.com');
    });
});
