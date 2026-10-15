<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Services\Voice\VoiceTurnGate;

/**
 * The voice gate refuses a deactivated workspace's calls before its other
 * checks, as `voice_not_available`, both when a call starts and before every
 * spoken reply.
 */
it('refuses a deactivated workspace as not available', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $gate = app(VoiceTurnGate::class);

    expect($gate->refusal($agent, null))->toBeNull();

    deactivateWorkspaceOf($agent);

    expect($gate->refusal($agent, null))->toBe(VoiceCallRefusal::NotAvailable);
});
