<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Speech\ReadAloudAvailability;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Operations\Media\NullSpeaker;
use Tests\Support\Llm\FakeSpeaker;

/**
 * Reading aloud is on for an agent only while the install has a
 * text-to-speech service and the agent turned it on; the agent's own voice
 * counts while the service has it.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    app()->instance(Speaker::class, new FakeSpeaker);
});

it('is on only when set up and turned on for the agent', function (): void {
    expect(app(ReadAloudAvailability::class)->isOnFor($this->agent))->toBeFalse();

    $this->agent->setSetting('allow_read_aloud', true, 'appearance');

    expect(app(ReadAloudAvailability::class)->isOnFor($this->agent->fresh()))->toBeTrue();

    app()->instance(Speaker::class, new NullSpeaker);

    expect(app(ReadAloudAvailability::class)->isOnFor($this->agent->fresh()))->toBeFalse();
});

it('reads with the agent\'s voice while the service has it, else the default', function (): void {
    $availability = app(ReadAloudAvailability::class);

    expect($availability->voiceFor($this->agent))->toBe('alto');

    $this->agent->setSetting('read_aloud_voice', 'tenor', 'appearance');
    expect($availability->voiceFor($this->agent->fresh()))->toBe('tenor');

    $this->agent->setSetting('read_aloud_voice', 'gone', 'appearance');
    expect($availability->voiceFor($this->agent->fresh()))->toBe('alto');
});
