<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\SaveGuardrailSettingsAction;
use Domain\Guardrail\Data\GuardrailSettingsData;

it('saves the settings and reads them back as the same configuration', function (): void {
    $agent = Agent::factory()->create();
    $settings = new GuardrailSettingsData(
        enabled: true,
        filters: ['email', 'phone'],
        injection: false,
        toxicity: true,
        blocked_topics: ['medical advice'],
        allowed_scope: 'Billing questions only',
        refusal_message: 'I can only help with billing.',
    );

    app(SaveGuardrailSettingsAction::class)->execute($agent, $settings);

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect($stored->toSettings())->toBe($settings->toSettings())
        ->and($stored->piiFilters())->toBe(['email', 'phone'])
        ->and($stored->hasTopicPolicy())->toBeTrue()
        ->and($stored->refusalMessage())->toBe('I can only help with billing.');
});

it('falls back to the defaults for an agent that never saved the form', function (): void {
    $settings = GuardrailSettingsData::fromAgent(Agent::factory()->create());

    expect($settings->enabled)->toBeFalse()
        ->and($settings->injection)->toBeTrue()
        ->and($settings->toxicity)->toBeTrue()
        ->and($settings->hasTopicPolicy())->toBeFalse()
        ->and($settings->refusalMessage())->toBe('Sorry, I can’t help with that request.');
});
