<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\SaveGuardrailSettingsAction;
use Domain\Guardrail\Data\BlockedTopicData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\TopicResponse;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Saving the Guardrails page stores the settings so they read back as the
 * same configuration, and translates the refusal message into the agent's
 * languages, keeping the typed texts when the translation fails. The
 * translator, the LLM boundary, is mocked.
 */
it('saves the settings, reads them back as the same configuration and translates the refusal', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['refusal' => ['de' => 'Nur Abrechnung.', 'fr' => 'Seulement la facturation.']]);
    $settings = new GuardrailSettingsData(
        enabled: true,
        filters: ['email', 'phone'],
        injection: false,
        toxicity: true,
        blocked_topics: [new BlockedTopicData('medical advice')],
        allowed_scope: 'Billing questions only',
        refusal_message: 'I can only help with billing.',
        allowlist: ['support@acme.com'],
    );

    app(SaveGuardrailSettingsAction::class)->execute($agent->fresh(), $settings);

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect(collect($stored->toSettings())->except('refusal_translations')->all())->toBe(collect($settings->toSettings())->except('refusal_translations')->all())
        ->and($stored->piiFilters())->toBe(['email', 'phone'])
        ->and($stored->hasTopicPolicy())->toBeTrue()
        ->and($stored->allowlist)->toBe(['support@acme.com'])
        ->and($stored->refusalMessage())->toBe('I can only help with billing.')
        ->and($stored->refusalMessage('de'))->toBe('Nur Abrechnung.')
        ->and($stored->refusalMessage('fr-CA'))->toBe('Seulement la facturation.')
        ->and($stored->refusalMessage('es'))->toBe('I can only help with billing.');
});

it('keeps the settings and shows the refusal and topic reply as typed when the translation fails', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de'], 'appearance');
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andThrow(new RuntimeException('translator down'));
    $settings = new GuardrailSettingsData(
        enabled: true,
        filters: [],
        injection: true,
        toxicity: true,
        blocked_topics: [new BlockedTopicData('Pricing', action: TopicResponse::Reply, reply: 'Please ask sales.')],
        allowed_scope: null,
        refusal_message: 'No.',
    );

    app(SaveGuardrailSettingsAction::class)->execute($agent->fresh(), $settings);

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect($stored->refusalMessage('de'))->toBe('No.')
        ->and($stored->blocked_topics[0]->replyIn('de'))->toBe('Please ask sales.');
});
