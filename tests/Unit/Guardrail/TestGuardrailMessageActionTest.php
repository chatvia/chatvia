<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\TestGuardrailMessageAction;
use Domain\Guardrail\Data\BlockedTopicData;
use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\TopicResponse;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Services\GuardrailClassifier;
use Illuminate\Support\Facades\Event;

/**
 * "Test a message" runs the input guards with the form's settings, not the
 * saved ones, and logs nothing.
 */
it('tests a message against unsaved settings without logging it', function (): void {
    Event::fake([GuardrailTriggered::class]);
    $this->mock(GuardrailClassifier::class)->shouldReceive('classify')
        ->andReturn(new ClassifierVerdictData(offTopic: true, language: 'en', matchedTopic: 1));

    $agent = Agent::factory()->create();
    $settings = new GuardrailSettingsData(
        enabled: true,
        filters: ['email'],
        injection: false,
        toxicity: false,
        blocked_topics: [new BlockedTopicData('Pricing', action: TopicResponse::Reply, reply: 'Please ask sales.')],
        allowed_scope: null,
        refusal_message: null,
    );

    $result = app(TestGuardrailMessageAction::class)->execute($agent, $settings, 'How much is it? I am jo@example.com');

    expect($result->blocked)->toBeTrue()
        ->and($result->refusalMessage)->toBe('Please ask sales.')
        ->and($result->text)->toBe('How much is it? I am [EMAIL_1]');

    Event::assertNotDispatched(GuardrailTriggered::class);
});
