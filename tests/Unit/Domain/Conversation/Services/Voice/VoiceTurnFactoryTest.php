<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Voice\VoiceTurnFactory;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * A voice call's transcript lists the sources under a spoken answer only when
 * the agent shows sources, the same rule as a typed turn.
 */
it('keeps the sources of spoken answers on the server by default', function (): void {
    $agent = Agent::factory()->create();

    expect(app(VoiceTurnFactory::class)->request(VoiceCalls::data($agent), $agent)->streamSources)->toBeFalse();
});

it('lists the sources of spoken answers when the agent shows them', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('show_sources', true, 'appearance');

    expect(app(VoiceTurnFactory::class)->request(VoiceCalls::data($agent), $agent)->streamSources)->toBeTrue();
});
