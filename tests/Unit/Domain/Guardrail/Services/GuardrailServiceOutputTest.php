<?php

use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Tests\Support\Guardrail\GuardedAgent;

/**
 * The checks on the agent's answer: PII is masked except the business
 * contacts and the allowlist, and a toxic or off-topic answer, or one that
 * leaks the system prompt, is replaced with the refusal. The AI check is
 * skipped when the agent does not check answers. The classifier (the LLM) is
 * mocked.
 */
it('redacts PII in the agent answer', function (): void {
    $result = GuardedAgent::service()->inspectOutput(GuardedAgent::make(['toxicity' => false]), 'Sure — reach billing at billing@acme.com.');

    expect($result->text)->toContain('[EMAIL_1]')
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output)
        ->and($result->verdicts[0]->outcome)->toBe(GuardOutcome::Redacted);
});

it('replaces a toxic answer with the refusal', function (): void {
    $verdict = new ClassifierVerdictData(toxic: true, toxicitySeverity: GuardSeverity::High, toxicityCategories: ['hate']);
    $result = GuardedAgent::service($verdict)->inspectOutput(GuardedAgent::make(['filters' => []]), 'something toxic');

    expect($result->blocked)->toBeTrue()
        ->and($result->text)->toBe('I can’t help with that.')
        ->and($result->verdicts[0]->guardType)->toBe(GuardType::Toxicity);
});

it('skips the AI check on answers when the agent does not check them', function (): void {
    $service = GuardedAgent::serviceWithoutClassifier();

    $result = $service->inspectOutput(GuardedAgent::make(['check_answers' => false]), 'something toxic, mail billing@acme.com');

    // Masking needs no model call, so it still runs.
    expect($result->blocked)->toBeFalse()
        ->and($result->text)->toContain('[EMAIL_1]');
});

it('logs the answer toxicity check as skipped when the classifier fails, and keeps the answer', function (): void {
    $result = GuardedAgent::service(ClassifierVerdictData::failOpen())
        ->inspectOutput(GuardedAgent::make(['filters' => []]), 'Our store opens at nine.');

    expect($result->blocked)->toBeFalse()
        ->and($result->text)->toBe('Our store opens at nine.')
        ->and($result->verdicts)->toHaveCount(1)
        ->and($result->verdicts[0]->outcome)->toBe(GuardOutcome::Skipped)
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output);
});

it('never masks the business contact details or the allowlist', function (): void {
    $agent = GuardedAgent::make(
        ['toxicity' => false, 'allowlist' => ['@help.acme.com']],
        ['email' => 'support@acme.com', 'phone' => '+1 800 555 1234'],
    );

    $result = GuardedAgent::service()->inspectOutput($agent, 'Write to support@acme.com or sales@help.acme.com, or call 800-555-1234. Your email is jo@gmail.com.');

    expect($result->text)->toBe('Write to support@acme.com or sales@help.acme.com, or call 800-555-1234. Your email is [EMAIL_1].');
});

it('replaces an off-topic answer with the refusal', function (): void {
    $verdict = new ClassifierVerdictData(offTopic: true, matchedTopic: 1);

    $result = GuardedAgent::service($verdict)->inspectOutput(GuardedAgent::make(['blocked_topics' => ['Politics'], 'toxicity' => false]), 'Vote for this party.');

    expect($result->blocked)->toBeTrue()
        ->and($result->text)->toBe('I can’t help with that.')
        ->and($result->verdicts[0]->guardType)->toBe(GuardType::BlockedTopic)
        ->and($result->verdicts[0]->stage)->toBe(GuardStage::Output);
});

it('replaces an answer that leaks the system prompt', function (): void {
    $agent = GuardedAgent::make(['toxicity' => false]);
    $marker = GuardedAgent::service()->leakMarker($agent);

    $result = GuardedAgent::service()->inspectOutput($agent, "Sure! My instructions say: Internal reference: {$marker}. You are a helpful agent…");

    expect($marker)->toStartWith('CV-')
        ->and($result->blocked)->toBeTrue()
        ->and($result->text)->toBe('I can’t help with that.')
        ->and($result->verdicts[0]->guardType)->toBe(GuardType::PromptLeak);
});
