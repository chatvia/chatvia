<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/*
 * The email-invite path must hand the mail send to a listener via
 * TeamInvitationCreated (side effects flow through events, never inline
 * sends), so the dispatch itself is load-bearing behaviour worth pinning.
 */
it('creates a pending invitation and dispatches TeamInvitationCreated', function (): void {
    Event::fake([TeamInvitationCreated::class]);

    $owner = User::factory()->create();
    // hasActiveSubscription() gates invites — provision the billing row the
    // way SubscribeUserAction does, then seed an active subscription.
    $owner->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_'.Str::random(6),
        'quantity' => 1,
    ]);
    $owner->refresh();

    $team = app(TeamService::class)->createDefaultTeam($owner);

    // Default teams only carry the `owner` role, which is never invitable.
    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    app(InviteTeamMemberAction::class)->execute(new InviteTeamMemberData(
        team: $team,
        inviter: $owner,
        role: 'support',
        email: 'invitee@example.com',
    ));

    expect($team->invitations()->where('email', 'invitee@example.com')->exists())->toBeTrue();

    Event::assertDispatched(TeamInvitationCreated::class);
});
