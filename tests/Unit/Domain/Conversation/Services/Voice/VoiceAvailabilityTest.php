<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Llm\Enums\AiProvider;

/**
 * Voice conversations are offered only when the install has them set up, has
 * a license key (voice is a free-use limit) and the agent has them on.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto']);

    $this->agent = Agent::factory()->create();
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
});

it('keeps voice off without a license key, even when set up and switched on', function (): void {
    $voice = app(VoiceAvailability::class);

    expect($voice->isSetUp())->toBeTrue()
        ->and($voice->isLicensed())->toBeFalse()
        ->and($voice->isOnFor($this->agent))->toBeFalse();
});

it('offers voice with a license key only while the agent has it on', function (): void {
    withLicense();
    $off = Agent::factory()->create();
    $off->setSetting('allow_voice_conversation', false, 'appearance');

    expect(app(VoiceAvailability::class)->isOnFor($this->agent))->toBeTrue()
        ->and(app(VoiceAvailability::class)->isOnFor($off))->toBeFalse();
});
