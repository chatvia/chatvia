<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Notifications\TeamInvitationNotification;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Support\Enums\User\UserType;

/*
 * HTTP-level coverage for the two invitation flows and their side effects:
 *
 *   teams.invite           — invitation row + TeamInvitationCreated, and (with
 *                            real events) the SendTeamInvitationEmail listener
 *                            registered in DomainServiceProvider mailing the
 *                            invitee on demand.
 *   agents.chatters.store   — InviteAgentChatterAction's row + chatter + pivot +
 *                            AgentChatterInvited, and the
 *                            SendAgentChatterInvitationEmail listener end to end.
 *   teams.cancel-invitation — owner deletes the row; non-owner is forbidden;
 *                            an invitation of another team 404s (scoped lookup).
 */

/**
 * A workspace owner with billing + active subscription + quota + default team.
 * Registration no longer auto-provisions any of these (trial-checkout only),
 * so seed them explicitly the way the Stripe webhook would.
 */
function invitationFlowOwner(): User
{
    $owner = User::factory()->create();

    $owner->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_'.Str::random(6),
        'quantity' => 1,
    ]);

    UserQuota::firstOrCreate(
        ['user_id' => $owner->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );

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

beforeEach(function (): void {
    $this->owner = invitationFlowOwner();
    $this->team = $this->owner->primaryTeam();
});

describe('teams.invite', function (): void {
    beforeEach(function (): void {
        invitationFlowSupportRole($this->team);
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
            fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'invitee@example.com',
        );
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
        $chatter = User::query()->where('email', 'chatter@example.com')->sole();

        expect($chatter->type)->toBe(UserType::Chatter)
            ->and($chatter->workspace_id)->toBe($this->owner->id)
            ->and($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();

        Event::assertDispatched(
            AgentChatterInvited::class,
            fn (AgentChatterInvited $event): bool => $event->agent->is($this->agent)
                && $event->email === 'chatter@example.com'
                && $event->acceptUrl === route('agents.invitation.show', ['token' => $invitation->token]),
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
            fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'chatter@example.com',
        );
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

    it('forbids a non-owner and keeps the row', function (): void {
        $outsider = invitationFlowOwner();

        $this->actingAs($outsider)
            ->from('/')
            ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
            ->assertForbidden();

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
