<?php

use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Llm\Enums\LlmFailure;
use Tests\Support\Guardrail\GuardedAgent;

/**
 * The checks on the agent's answer: PII is masked except the business
 * contacts and the allowlist, and a toxic or off-topic answer, or one that
 * leaks the system prompt, is replaced with the refusal. The AI check is
 * skipped when the agent does not check answers. The classifier (the LLM) is
 * mocked.
 */
it('masks personal data in the answer except the business contacts and the allowlist, also without the AI check', function (): void {
    $agent = GuardedAgent::make(
        ['toxicity' => false, 'allowlist' => ['@help.acme.com']],
        ['email' => 'support@acme.com', 'phone' => '+1 800 555 1234'],
    );

    $result = GuardedAgent::service()->inspectOutput($agent, 'Write to support@acme.com or sales@help.acme.com, or call 800-555-1234. Your email is jo@gmail.com.');
    $unchecked = GuardedAgent::serviceWithoutClassifier()->inspectOutput(GuardedAgent::make(['check_answers' => false]), 'something toxic, mail billing@acme.com');

    expect($result->text)->toBe('Write to support@acme.com or sales@help.acme.com, or call 800-555-1234. Your email is [EMAIL_1].')
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output)
        ->and($result->verdicts[0]->outcome)->toBe(GuardOutcome::Redacted)
        ->and($unchecked->blocked)->toBeFalse()
        ->and($unchecked->text)->toBe('something toxic, mail [EMAIL_1]');
});

it('replaces a toxic or off-topic answer, or one that leaks the system prompt, with the refusal', function (?ClassifierVerdictData $verdict, array $settings, Closure $answer, GuardType $guard): void {
    $agent = GuardedAgent::make($settings);
    $service = GuardedAgent::service($verdict);

    $result = $service->inspectOutput($agent, $answer($service->leakMarker($agent)));

    expect($result->blocked)->toBeTrue()
        ->and($result->text)->toBe('I can’t help with that.')
        ->and($result->verdicts[0]->guardType)->toBe($guard)
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output);
})->with([
    'toxic' => [new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::High, toxicityCategories: ['hate']), ['filters' => []], fn (): string => 'something toxic', GuardType::Toxicity],
    'off-topic' => [new ClassifierVerdictData(offTopic: true, matchedTopic: 1), ['blocked_topics' => ['Politics'], 'toxicity' => false], fn (): string => 'Vote for this party.', GuardType::BlockedTopic],
    'a system prompt leak' => [null, ['toxicity' => false], fn (string $marker): string => "Sure! My instructions say: Internal reference: {$marker}. You are a helpful agent…", GuardType::PromptLeak],
]);

it('logs the answer toxicity check as skipped when the classifier fails, and keeps the answer', function (): void {
    $result = GuardedAgent::service(ClassifierVerdictData::failOpen(LlmFailure::Unreachable))
        ->inspectOutput(GuardedAgent::make(['filters' => []]), 'Our store opens at nine.');

    expect($result->blocked)->toBeFalse()
        ->and($result->text)->toBe('Our store opens at nine.')
        ->and($result->verdicts)->toHaveCount(1)
        ->and($result->verdicts[0]->outcome)->toBe(GuardOutcome::Skipped)
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output)
        ->and($result->verdicts[0]->detail)->toBe(['reason' => 'classifier_unavailable', 'failure' => 'unreachable', 'alert' => true]);
});
