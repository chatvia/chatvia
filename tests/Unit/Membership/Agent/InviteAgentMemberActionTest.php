<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteAgentMemberAction;
use Domain\Membership\Agent\Data\InviteAgentMemberData;
use Domain\Membership\Agent\Events\AgentMemberInvited;
use Domain\Membership\Agent\Exceptions\AgentMemberInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentMemberInvitation;
use Illuminate\Support\Facades\Event;
use Support\Enums\User\UserType;

/**
 * InviteAgentMemberAction is the FIRST line of defence in the agent-invitation
 * flow (AcceptAgentInvitationAction is the second). Its two rejection branches
 * are account-takeover guards and its idempotency stops forwarded-link replay,
 * so each gets a behaviour test here.
 */
function inviteWorkspaceAndAgent(string $ownerEmail = 'owner@example.com'): array
{
    $workspace = User::factory()->create([
        'type' => UserType::Workspace,
        'email' => $ownerEmail,
    ]);

    $agent = Agent::factory()->create(['user_id' => $workspace->id]);

    return [$workspace, $agent];
}

function invite(Agent $agent, string $email): \Domain\Membership\Agent\Data\AgentMemberInvitationData
{
    return app(InviteAgentMemberAction::class)->execute(new InviteAgentMemberData(
        agent: $agent,
        email: $email,
    ));
}

it('provisions a new chatter, attaches the agent pivot, and dispatches AgentMemberInvited', function (): void {
    Event::fake([AgentMemberInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $result = invite($agent, 'new@example.com');

    $chatter = User::query()->where('email', 'new@example.com')->first();

    expect($chatter)->not->toBeNull()
        ->and($chatter->type)->toBe(UserType::Chatter)
        ->and($chatter->workspace_id)->toBe($workspace->id)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and(AgentMemberInvitation::query()->where('agent_id', $agent->id)->where('email', 'new@example.com')->count())->toBe(1)
        ->and($result->email)->toBe('new@example.com');

    Event::assertDispatched(AgentMemberInvited::class);
});

it('rejects an email that belongs to a non-chatter account', function (): void {
    [, $agent] = inviteWorkspaceAndAgent();
    User::factory()->create(['type' => UserType::Workspace, 'email' => 'boss@example.com']);

    expect(fn () => invite($agent, 'boss@example.com'))
        ->toThrow(AgentMemberInvitationRejectedException::class);
});

it('rejects an email owned by a chatter under a different workspace', function (): void {
    [, $agent] = inviteWorkspaceAndAgent();

    $otherWorkspace = User::factory()->create(['type' => UserType::Workspace, 'email' => 'other-owner@example.com']);
    User::factory()->create([
        'type' => UserType::Chatter,
        'workspace_id' => $otherWorkspace->id,
        'email' => 'foreign@example.com',
    ]);

    expect(fn () => invite($agent, 'foreign@example.com'))
        ->toThrow(AgentMemberInvitationRejectedException::class);
});

it('is idempotent: re-inviting the same email rotates the token and keeps one pending row + one chatter', function (): void {
    Event::fake([AgentMemberInvited::class]);
    [, $agent] = inviteWorkspaceAndAgent();

    invite($agent, 'repeat@example.com');
    $firstToken = AgentMemberInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com')->value('token');

    invite($agent, 'repeat@example.com');

    $rows = AgentMemberInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com');

    expect($rows->count())->toBe(1)
        ->and($rows->value('token'))->not->toBe($firstToken)
        ->and(User::query()->where('email', 'repeat@example.com')->count())->toBe(1);
});

it('reuses an existing chatter already under the same workspace', function (): void {
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $existing = User::factory()->create([
        'type' => UserType::Chatter,
        'workspace_id' => $workspace->id,
        'email' => 'existing@example.com',
    ]);

    invite($agent, 'existing@example.com');

    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1)
        ->and($agent->chatters()->whereKey($existing->id)->exists())->toBeTrue();
});
