<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\InviteWorkspaceChatterAction;
use Domain\Membership\Agent\Data\InviteWorkspaceChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * InviteWorkspaceChatterAction is the workspace-page entry point: it provisions
 * one chatter and grants several agents at once, reusing InviteAgentChatterAction
 * (first agent) + GrantAgentMembershipAction (the rest). These tests pin the
 * multi-agent grant, that a single invitation is minted, and that an email
 * known in another workspace becomes a separate chatter of this one.
 */
function workspaceWithAgents(int $count = 2): array
{
    $workspace = User::factory()->create();
    $agents = Agent::factory()->count($count)->create(['workspace_id' => $workspace->workspace()->id]);

    return [$workspace, $agents];
}

it('provisions the chatter and grants every selected agent', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agents] = workspaceWithAgents(3);

    $invitation = app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'new@example.com',
        agents: $agents,
    ));

    $chatter = $workspace->workspace()->chatters()->where('email', 'new@example.com')->first();

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->workspace_id)->toBe($workspace->workspace()->id)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($invitation->email)->toBe('new@example.com');

    // Every selected agent is granted to the chatter.
    foreach ($agents as $agent) {
        expect($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();
    }

    // Exactly one invitation row (against the first agent) drives the accept link.
    expect(AgentChatterInvitation::query()->where('email', 'new@example.com')->count())->toBe(1)
        ->and(AgentChatterInvitation::query()->where('agent_id', $agents->first()->id)->where('email', 'new@example.com')->exists())->toBeTrue();

    Event::assertDispatched(AgentChatterInvited::class);
});

it('grants the selected agents to an existing chatter under the same workspace', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agents] = workspaceWithAgents(2);

    $existing = Chatter::factory()->ofWorkspace($workspace)->create(['email' => 'member@example.com']);

    app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'member@example.com',
        agents: $agents,
    ));

    expect($workspace->workspace()->chatters()->where('email', 'member@example.com')->count())->toBe(1)
        ->and($agents[0]->chatters()->whereKey($existing->id)->exists())->toBeTrue()
        ->and($agents[1]->chatters()->whereKey($existing->id)->exists())->toBeTrue();
});

it('creates a separate chatter when the email is a chatter of another workspace', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agents] = workspaceWithAgents(2);

    [, $otherAgents] = workspaceWithAgents(1);
    $foreign = Chatter::factory()->grantedTo($otherAgents->first())->create(['email' => 'shared@example.com']);

    $invitation = app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'shared@example.com',
        agents: $agents,
    ));

    $chatter = $workspace->workspace()->chatters()->where('email', 'shared@example.com')->sole();

    expect($invitation->email)->toBe('shared@example.com')
        ->and($chatter->id)->not->toBe($foreign->id)
        ->and($agents[0]->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($agents[1]->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        // The other workspace's chatter keeps only its own grant.
        ->and($foreign->fresh()->agents()->pluck('agents.id')->all())->toBe([$otherAgents->first()->id]);
});

it('creates a chatter for the email of a staff account', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agents] = workspaceWithAgents(2);
    User::factory()->employee($workspace)->create(['email' => 'teammate@example.com']);

    app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'teammate@example.com',
        agents: $agents,
    ));

    $chatter = $workspace->workspace()->chatters()->where('email', 'teammate@example.com')->sole();

    expect($agents[0]->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($agents[1]->chatters()->whereKey($chatter->id)->exists())->toBeTrue();
});

it('sends the invitation email with the token link once the transaction commits', function (): void {
    Notification::fake();
    [, $agents] = workspaceWithAgents(2);

    $result = app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'new@example.com',
        agents: $agents,
    ));

    Notification::assertSentOnDemand(
        AgentChatterInvitationNotification::class,
        fn (AgentChatterInvitationNotification $notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'new@example.com'
            && $notification->acceptUrl() === route('agents.invitation.show', ['token' => $result->token]),
    );
});

it('sends no invitation email when a later step fails and the transaction rolls back', function (): void {
    Notification::fake();
    [, $agents] = workspaceWithAgents(2);

    $grant = Mockery::mock(GrantAgentMembershipAction::class);
    $grant->shouldReceive('execute')->andThrow(new RuntimeException('grant failed'));
    app()->instance(GrantAgentMembershipAction::class, $grant);

    expect(fn () => app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'new@example.com',
        agents: $agents,
    )))->toThrow(RuntimeException::class, 'grant failed');

    // The token was rolled back, so an email carrying it would be a dead link.
    expect(AgentChatterInvitation::query()->where('email', 'new@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});
