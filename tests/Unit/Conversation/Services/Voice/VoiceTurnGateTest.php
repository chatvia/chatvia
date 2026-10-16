<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\Voice\VoiceTurnGate;

/**
 * The voice gate runs the checks of a typed turn when a call starts and
 * before every spoken reply: a deactivated workspace (`voice_not_available`,
 * before the other checks), the workspace's and the chatter's monthly
 * message limits, and the agent's messages-per-minute cap.
 */
it('refuses a deactivated workspace as not available', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $gate = app(VoiceTurnGate::class);

    expect($gate->refusal($agent, null))->toBeNull();

    deactivateWorkspaceOf($agent);

    expect($gate->refusal($agent, null))->toBe(VoiceCallRefusal::NotAvailable);
});

/** A limit already used up this month, for the workspace or the chatter given. */
function vtgReachedLimit(array $owner): void
{
    MessageLimit::create([...$owner, 'monthly_limit' => 2, 'messages_used' => 2, 'period_starts_at' => now()->startOfMonth()]);
}

it('refuses once the workspace has used its monthly messages', function (): void {
    $agent = Agent::factory()->create();
    vtgReachedLimit(['workspace_id' => $agent->workspace_id]);

    expect(app(VoiceTurnGate::class)->refusal($agent, null))->toBe(VoiceCallRefusal::MessageLimitReached);
});

it('refuses a chatter who has used their own monthly messages, and only them', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $other = Chatter::factory()->grantedTo($agent)->create();
    vtgReachedLimit(['chatter_id' => $chatter->id]);
    $gate = app(VoiceTurnGate::class);

    expect($gate->refusal($agent, $chatter->id))->toBe(VoiceCallRefusal::ChatterMessageLimitReached)
        ->and($gate->refusal($agent, $other->id))->toBeNull()
        ->and($gate->refusal($agent, null))->toBeNull();
});

it('refuses while the agent is over its messages-per-minute cap', function (): void {
    $agent = Agent::factory()->create();
    $this->mock(AgentChatRateLimiter::class)->shouldReceive('exceeded')->andReturnTrue();

    expect(app(VoiceTurnGate::class)->refusal($agent, null))->toBe(VoiceCallRefusal::RateLimited);
});
