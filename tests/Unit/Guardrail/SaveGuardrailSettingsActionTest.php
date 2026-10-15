<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\SaveGuardrailSettingsAction;
use Domain\Guardrail\Data\BlockedTopicData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\TopicResponse;
use Domain\Llm\Operations\Translate\TextTranslator;

beforeEach(function (): void {
    // Default: no real LLM calls. Tests that assert translations re-mock below.
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('saves the settings and reads them back as the same configuration', function (): void {
    $agent = Agent::factory()->create();
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

    app(SaveGuardrailSettingsAction::class)->execute($agent, $settings);

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect($stored->toSettings())->toBe($settings->toSettings())
        ->and($stored->piiFilters())->toBe(['email', 'phone'])
        ->and($stored->hasTopicPolicy())->toBeTrue()
        ->and($stored->allowlist)->toBe(['support@acme.com'])
        ->and($stored->refusalMessage())->toBe('I can only help with billing.');
});

it('falls back to the defaults for an agent that never saved the form', function (): void {
    $settings = GuardrailSettingsData::fromAgent(Agent::factory()->create());

    expect($settings->enabled)->toBeTrue()
        ->and($settings->injection)->toBeTrue()
        ->and($settings->toxicity)->toBeTrue()
        ->and($settings->hasTopicPolicy())->toBeFalse()
        ->and($settings->refusalMessage())->toBe('Sorry, I can’t help with that request.');
});

function guardrailSettings(?string $refusal): GuardrailSettingsData
{
    return new GuardrailSettingsData(
        enabled: true,
        filters: [],
        injection: true,
        toxicity: true,
        blocked_topics: [],
        allowed_scope: null,
        refusal_message: $refusal,
    );
}

it('translates the refusal message into the agent languages', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['refusal' => ['de' => 'Dabei kann ich nicht helfen.', 'fr' => 'Je ne peux pas vous aider.']]);

    app(SaveGuardrailSettingsAction::class)->execute($agent->fresh(), guardrailSettings('I can’t help with that.'));

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect($stored->refusalMessage('de'))->toBe('Dabei kann ich nicht helfen.')
        ->and($stored->refusalMessage('fr-CA'))->toBe('Je ne peux pas vous aider.')
        ->and($stored->refusalMessage('es'))->toBe('I can’t help with that.')
        ->and($stored->refusalMessage())->toBe('I can’t help with that.');
});

it('does not translate an unchanged refusal again', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['refusal' => ['de' => 'Dabei kann ich nicht helfen.']]);

    $action = app(SaveGuardrailSettingsAction::class);
    $action->execute($agent->fresh(), guardrailSettings('I can’t help with that.'));
    $action->execute($agent->fresh(), guardrailSettings('I can’t help with that.'));

    expect(GuardrailSettingsData::fromAgent($agent->fresh())->refusalMessage('de'))->toBe('Dabei kann ich nicht helfen.');
});

it('keeps the settings and shows the refusal as typed when the translation fails', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andThrow(new RuntimeException('translator down'));

    app(SaveGuardrailSettingsAction::class)->execute($agent->fresh(), guardrailSettings('I can’t help with that.'));

    $stored = GuardrailSettingsData::fromAgent($agent->fresh());

    expect($stored->refusal_message)->toBe('I can’t help with that.')
        ->and($stored->refusalMessage('de'))->toBe('I can’t help with that.');
});

it('translates each blocked topic reply with the refusal', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['refusal' => ['de' => 'Nein.'], 'topic_0' => ['de' => 'Bitte frag den Vertrieb.']]);

    $settings = guardrailSettings('No.');
    $settings->blocked_topics = [new BlockedTopicData('Pricing', action: TopicResponse::Reply, reply: 'Please ask sales.')];

    app(SaveGuardrailSettingsAction::class)->execute($agent->fresh(), $settings);

    expect(GuardrailSettingsData::fromAgent($agent->fresh())->blocked_topics[0]->replyIn('de'))->toBe('Bitte frag den Vertrieb.');
});
