<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Handoff\HandoffAvailability;

/**
 * Live chat is offered only when the install has a license key (live chat is
 * a free-use limit) and the agent has it on.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
});

it('keeps live chat off without a license key, even when the agent has it on', function (): void {
    $handoff = app(HandoffAvailability::class);

    expect($this->agent->handoffEnabled())->toBeTrue()
        ->and($handoff->isLicensed())->toBeFalse()
        ->and($handoff->isOnFor($this->agent))->toBeFalse();
});

it('offers live chat with a license key', function (): void {
    withLicense();

    expect(app(HandoffAvailability::class)->isOnFor($this->agent))->toBeTrue();
});

it('keeps live chat off with a license key while the agent has it off', function (): void {
    withLicense();
    $this->agent->setSetting('handoff_enabled', false, 'widget');

    expect(app(HandoffAvailability::class)->isOnFor($this->agent))->toBeFalse();
});
