<?php

use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardType;
use Domain\Llm\Enums\LlmFailure;
use Tests\Support\Guardrail\GuardedAgent;

/**
 * The checks on a visitor's message: PII is masked (or blocks the turn when
 * the owner says so), prompt injection, a blocked topic and toxicity are
 * blocked or flagged by mode and sensitivity, and the refusal comes in the
 * message's language (a blocked topic may answer with its own reply or offer
 * a handoff). A failed classifier logs every check as skipped and lets the
 * message through. The classifier (the LLM) is mocked.
 */
it('masks personal data by the enabled filters and owner patterns, blocking a type the owner set to block', function (array $settings, string $text, string $masked, bool $blocked, GuardOutcome $outcome, ?string $refusal): void {
    $result = GuardedAgent::service()->inspectInput(GuardedAgent::make($settings), $text);

    expect($result->text)->toBe($masked)
        ->and($result->blocked)->toBe($blocked)
        ->and($result->refusalMessage)->toBe($refusal)
        ->and($result->verdicts)->toHaveCount(1)
        ->and($result->verdicts[0]->guardType)->toBe(GuardType::Pii)
        ->and($result->verdicts[0]->outcome)->toBe($outcome);
})->with([
    'the default filters' => [[], 'Email me at a@b.com', 'Email me at [EMAIL_1]', false, GuardOutcome::Redacted, null],
    'only the enabled filters' => [['filters' => ['us_ssn']], 'a@b.com SSN 123-45-6789', 'a@b.com SSN [US_SSN_1]', false, GuardOutcome::Redacted, null],
    'an owner-defined pattern' => [['custom_patterns' => [['name' => 'Customer number', 'pattern' => 'K-\d{6}', 'action' => 'mask']]], 'I am K-123456', 'I am [CUSTOMER_NUMBER_1]', false, GuardOutcome::Redacted, null],
    'a type the owner set to block' => [['pii_actions' => ['credit_card' => 'block']], 'My card is 4111 1111 1111 1111', 'My card is [CARD_1]', true, GuardOutcome::Blocked, 'For your safety, please do not share this kind of personal data in the chat.'],
]);

it('blocks or flags injection, a blocked topic and toxicity by mode and sensitivity', function (ClassifierVerdictData $verdict, array $settings, bool $blocked, GuardType $guard, GuardOutcome $outcome): void {
    $result = GuardedAgent::service($verdict)->inspectInput(GuardedAgent::make($settings), 'a visitor message');

    expect($result->blocked)->toBe($blocked)
        ->and(collect($result->verdicts)->firstWhere('guardType', $guard)?->outcome)->toBe($outcome);
})->with([
    'injection' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High, reason: 'override attempt'), [], true, GuardType::PromptInjection, GuardOutcome::Blocked],
    'injection in monitor mode' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High), ['injection_mode' => 'monitor'], false, GuardType::PromptInjection, GuardOutcome::Flagged],
    'an off-topic message under a topic policy' => [new ClassifierVerdictData(offTopic: true, reason: 'medical advice'), ['blocked_topics' => ['medical advice']], true, GuardType::BlockedTopic, GuardOutcome::Blocked],
    'low toxicity at the default sensitivity' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::Low, toxicityCategories: ['harassment']), ['injection' => false], false, GuardType::Toxicity, GuardOutcome::Flagged],
    'high sensitivity blocks low toxicity' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::Low), ['injection' => false, 'toxicity_sensitivity' => 'high'], true, GuardType::Toxicity, GuardOutcome::Blocked],
    'medium sensitivity blocks medium toxicity' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::Medium), ['injection' => false, 'toxicity_sensitivity' => 'medium'], true, GuardType::Toxicity, GuardOutcome::Blocked],
    'low sensitivity flags medium toxicity' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::Medium), ['injection' => false, 'toxicity_sensitivity' => 'low'], false, GuardType::Toxicity, GuardOutcome::Flagged],
    'low sensitivity blocks high toxicity' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::High), ['injection' => false, 'toxicity_sensitivity' => 'low'], true, GuardType::Toxicity, GuardOutcome::Blocked],
]);

it('answers a block in the message language, with a topic\'s own reply or handoff unless an injection came too', function (ClassifierVerdictData $verdict, array $settings, string $refusal, ?string $handoff): void {
    $result = GuardedAgent::service($verdict)->inspectInput(GuardedAgent::make($settings), 'a visitor message');

    expect($result->blocked)->toBeTrue()
        ->and($result->refusalMessage)->toBe($refusal)
        ->and($result->handoffMessage)->toBe($handoff);
})->with([
    'the owner\'s refusal' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High), [], 'I can’t help with that.', null],
    'the owner\'s refusal in the message language' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High, language: 'de'), ['refusal_translations' => ['de' => 'Dabei kann ich nicht helfen.']], 'Dabei kann ich nicht helfen.', null],
    'the generic refusal when the owner wrote none' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High, language: 'de'), ['refusal_message' => ''], 'Entschuldigung, bei dieser Anfrage kann ich nicht helfen.', null],
    'a topic\'s own reply in the visitor language' => [new ClassifierVerdictData(offTopic: true, language: 'de', matchedTopic: 1), ['blocked_topics' => [['name' => 'Pricing', 'action' => 'reply', 'reply' => 'Please ask sales.', 'reply_translations' => ['de' => 'Bitte frag den Vertrieb.']]], 'injection' => false, 'toxicity' => false], 'Bitte frag den Vertrieb.', null],
    'a topic set to hand off' => [new ClassifierVerdictData(offTopic: true, language: 'en', matchedTopic: 1), ['blocked_topics' => [['name' => 'Complaints', 'action' => 'handoff']], 'injection' => false, 'toxicity' => false], 'I can’t help with that.', 'Let me connect you with someone from our team.'],
    'a topic block that came with an injection' => [new ClassifierVerdictData(injection: true, injectionRisk: GuardSeverity::High, offTopic: true, matchedTopic: 1), ['blocked_topics' => [['name' => 'Pricing', 'action' => 'reply', 'reply' => 'Please ask sales.']]], 'I can’t help with that.', null],
]);

it('logs every enabled check as skipped when the classifier fails, and lets the message through', function (): void {
    $result = GuardedAgent::service(ClassifierVerdictData::failOpen(LlmFailure::ModelUnavailable))
        ->inspectInput(GuardedAgent::make(['blocked_topics' => ['politics']]), 'hello');

    expect($result->blocked)->toBeFalse()
        ->and(collect($result->verdicts)->pluck('outcome')->unique()->all())->toBe([GuardOutcome::Skipped])
        ->and(collect($result->verdicts)->pluck('guardType')->all())
        ->toBe([GuardType::PromptInjection, GuardType::BlockedTopic, GuardType::Toxicity])
        ->and(collect($result->verdicts)->pluck('detail.failure')->unique()->all())->toBe(['model_unavailable'])
        ->and(collect($result->verdicts)->pluck('detail.alert')->all())->toBe([true, null, null]);
});
