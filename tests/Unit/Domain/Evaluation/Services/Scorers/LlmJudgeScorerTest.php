<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\Scorers\LlmJudgeScorer;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Tests\Support\Evaluation\EvalFixtures;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * The LLM judge is the only scorer with an external dependency, so its contract
 * is: shape the prompt, read the constrained verdict, and — critically — FAIL
 * (never silently pass) when the judge errors or returns garbage, so a provider
 * outage can't be mistaken for a green eval.
 */
/** @param  array<string, mixed>  $config */
function ljsCase(array $config = [], ?string $expected = null): EvalCase
{
    return EvalFixtures::case(ScorerType::LlmJudge, $config, $expected, input: 'What is your refund window?');
}

function ljsScorer(CannedLlmProvider $provider): LlmJudgeScorer
{
    return new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(TaskModels::class));
}

it('passes when the judge returns a positive verdict', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['verdict' => true, 'score' => 0.9, 'rationale' => 'States the 30-day window.']));

    $result = ljsScorer($provider)->score(ljsCase(['criteria' => 'Must mention 30 days.']), EvalFixtures::run('Refunds within 30 days.'));

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(0.9)
        ->and($result->rationale)->toContain('30-day');
});

it('fails when the judge returns a negative verdict', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['verdict' => false, 'score' => 0.1, 'rationale' => 'No refund window stated.']));

    $result = ljsScorer($provider)->score(ljsCase(['criteria' => 'Must mention 30 days.']), EvalFixtures::run('We do not offer refunds.'));

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.1);
});

it('clamps an out-of-range score into 0..1', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['verdict' => true, 'score' => 4.5, 'rationale' => 'ok']));

    $result = ljsScorer($provider)->score(ljsCase(['criteria' => 'x']), EvalFixtures::run('answer'));

    expect($result->score)->toBe(1.0);
});

it('fails (not passes) when the judge call throws', function (): void {
    $provider = CannedLlmProvider::failing();

    $result = ljsScorer($provider)->score(ljsCase(['criteria' => 'x']), EvalFixtures::run('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('Judge call failed');
});

it('fails when the judge returns unparseable content', function (): void {
    $provider = CannedLlmProvider::answering('I cannot produce JSON today.');

    $result = ljsScorer($provider)->score(ljsCase(['criteria' => 'x']), EvalFixtures::run('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('no parseable verdict');
});

it('fails without calling the judge when no criteria are configured', function (): void {
    $provider = CannedLlmProvider::failing();

    $result = ljsScorer($provider)->score(ljsCase([]), EvalFixtures::run('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('No grading criteria')
        ->and($provider->completions)->toBe([]);
});

it('shows the judge the question, the criteria, the reference answer and the answer', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['verdict' => true, 'score' => 1, 'rationale' => 'ok']));

    ljsScorer($provider)->score(
        ljsCase(['criteria' => 'Must mention 30 days.'], expected: 'Refunds are possible within 30 days.'),
        EvalFixtures::run('You can get a refund for a month.'),
    );

    $prompt = collect($provider->completions[0]['messages'])->firstWhere('role', 'user')['content'];

    expect($provider->completions)->toHaveCount(1)
        ->and($provider->completions[0]['temperature'])->toBe(0.0)
        ->and($prompt)->toContain('What is your refund window?')
        ->and($prompt)->toContain('Must mention 30 days.')
        ->and($prompt)->toContain('Refunds are possible within 30 days.')
        ->and($prompt)->toContain('You can get a refund for a month.');
});
