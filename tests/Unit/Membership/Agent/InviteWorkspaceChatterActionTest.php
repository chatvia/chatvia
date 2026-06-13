<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteWorkspaceChatterAction;
use Domain\Membership\Agent\Data\InviteWorkspaceChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\Event;
use Support\Enums\User\UserType;

/**
 * InviteWorkspaceChatterAction is the workspace-page entry point: it provisions
 * one chatter and grants several agents at once, reusing InviteAgentChatterAction
 * (first agent) + GrantAgentMembershipAction (the rest). These tests pin the
 * multi-agent grant and that a single invitation is minted.
 */
function workspaceWithAgents(int $count = 2): array
{
    $workspace = User::factory()->create(['type' => UserType::Workspace]);
    $agents = Agent::factory()->count($count)->create(['user_id' => $workspace->id]);

    return [$workspace, $agents];
}

it('provisions the chatter and grants every selected agent', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agents] = workspaceWithAgents(3);

    $invitation = app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'new@example.com',
        agents: $agents,
    ));

    $chatter = User::query()->where('email', 'new@example.com')->first();

    expect($chatter)->not->toBeNull()
        ->and($chatter->type)->toBe(UserType::Chatter)
        ->and($chatter->workspace_id)->toBe($workspace->id)
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

    $existing = User::factory()->create([
        'type' => UserType::Chatter,
        'email' => 'member@example.com',
        'workspace_id' => $workspace->id,
    ]);

    app(InviteWorkspaceChatterAction::class)->execute(new InviteWorkspaceChatterData(
        email: 'member@example.com',
        agents: $agents,
    ));

    expect(User::query()->where('email', 'member@example.com')->count())->toBe(1)
        ->and($agents[0]->chatters()->whereKey($existing->id)->exists())->toBeTrue()
        ->and($agents[1]->chatters()->whereKey($existing->id)->exists())->toBeTrue();
});
