<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailSettingsData;

/**
 * An agent that never saved the Guardrails page is protected by the defaults:
 * the injection and toxicity checks on, PII redaction off, no topic policy,
 * and the generic refusal.
 */
it('falls back to the defaults for an agent that never saved the form', function (): void {
    $settings = GuardrailSettingsData::fromAgent(Agent::factory()->create());

    expect($settings->enabled)->toBeTrue()
        ->and($settings->injection)->toBeTrue()
        ->and($settings->toxicity)->toBeTrue()
        ->and($settings->filters)->toBe([])
        ->and($settings->piiFilters())->toBe([])
        ->and($settings->hasTopicPolicy())->toBeFalse()
        ->and($settings->refusalMessage())->toBe('Sorry, I can’t help with that request.');
});
