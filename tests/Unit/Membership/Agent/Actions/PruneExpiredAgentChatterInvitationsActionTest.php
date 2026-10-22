<?php

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\PruneExpiredAgentChatterInvitationsAction;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Str;

/**
 * Chatter invitations are deleted 30 days after their link expired; a live
 * link and one that expired recently stay.
 */
function peaciInvitation(Agent $agent, string $expiresAt): AgentChatterInvitation
{
    return AgentChatterInvitation::query()->create([
        'agent_id' => $agent->id,
        'email' => Str::random(8).'@example.test',
        'token' => Str::random(64),
        'expires_at' => $expiresAt,
    ]);
}

it('deletes invitations that expired more than 30 days ago', function (): void {
    $agent = Agent::factory()->create();
    $old = [peaciInvitation($agent, now()->subDays(31))->id, peaciInvitation($agent, now()->subDays(90))->id];
    $kept = [peaciInvitation($agent, now()->subDays(29))->id, peaciInvitation($agent, now()->addDays(5))->id];

    $deleted = app(PruneExpiredAgentChatterInvitationsAction::class)->execute(batchSize: 1);

    expect($deleted)->toBe(2)
        ->and(AgentChatterInvitation::query()->whereKey($old)->exists())->toBeFalse()
        ->and(AgentChatterInvitation::query()->whereKey($kept)->count())->toBe(2);
});
