<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Services\Voice\VoiceTurnFactory;
use Illuminate\Support\Str;

/**
 * A voice call's transcript lists the sources under a spoken answer only when
 * the agent shows sources, the same rule as a typed turn.
 */
function voiceTurnFactoryCall(Agent $agent): VoiceCallData
{
    return new VoiceCallData(
        call_id: Str::uuid7()->toString(),
        provider_call_id: 'rtc_test',
        agent_id: $agent->id,
        workspace_id: (int) $agent->workspace_id,
        surface: 'hosted_chat',
        session_id: 'session-1',
        conversation_id: 'conversation-1',
        model: 'gpt-realtime-2.1',
        voice: 'marin',
        started_at: now()->getTimestamp(),
        max_seconds: 600,
    );
}

it('keeps the sources of spoken answers on the server by default', function (): void {
    $agent = Agent::factory()->create();

    expect(app(VoiceTurnFactory::class)->request(voiceTurnFactoryCall($agent), $agent)->streamSources)->toBeFalse();
});

it('lists the sources of spoken answers when the agent shows them', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('show_sources', true, 'appearance');

    expect(app(VoiceTurnFactory::class)->request(voiceTurnFactoryCall($agent), $agent)->streamSources)->toBeTrue();
});
