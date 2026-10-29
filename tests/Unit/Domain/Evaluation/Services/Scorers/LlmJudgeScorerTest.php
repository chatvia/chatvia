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
 * is: read the constrained verdict, and — critically — FAIL (never silently
 * pass) when the judge errors or returns garbage, so a provider outage can't
 * be mistaken for a green eval.
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
        ->and($result->score)->toBe(0.9);
});

it('fails, never passes, when the judge call throws or returns unparseable content', function (Closure $provider): void {
    $result = ljsScorer($provider())->score(ljsCase(['criteria' => 'x']), EvalFixtures::run('answer'));

    expect($result->pass)->toBeFalse();
})->with([
    'the call throws' => [fn (): CannedLlmProvider => CannedLlmProvider::failing()],
    'unparseable content' => [fn (): CannedLlmProvider => CannedLlmProvider::answering('I cannot produce JSON today.')],
]);
