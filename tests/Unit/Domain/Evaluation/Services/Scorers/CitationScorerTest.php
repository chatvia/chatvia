<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\Scorers\CitationScorer;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Tests\Support\Evaluation\EvalFixtures;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * The citations check: the share of cited sentences whose passages support
 * them, with a made-up citation (a block the agent was not given) counted as
 * unsupported. An answer that cites nothing, or a judge that fails, fails.
 */
/** @param  array<string, mixed>  $config */
function csCase(array $config = []): EvalCase
{
    return EvalFixtures::case(ScorerType::Citations, $config, null, input: 'How long do refunds take, and is shipping free?');
}

function csScorer(CannedLlmProvider $provider): CitationScorer
{
    return new CitationScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(TaskModels::class));
}

it('scores the share of citations the cited passages support', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['supported' => [true, false]]));
    $run = EvalFixtures::run('Refunds take five days[1]. Shipping is always free[2]. We love you[9].', blocks: ['Refunds take five days.', 'Shipping costs 5 EUR.']);

    $result = csScorer($provider)->score(csCase(), $run);

    // Two checked by the judge (one supported), and [9] cites a block the agent never had.
    expect($result->score)->toBe(0.3333)
        ->and($result->pass)->toBeFalse()
        ->and($result->rationale)->toBe('1 of 3 cited sentences are supported by the passage they cite.');
});

it('passes at the case\'s own bar', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['supported' => [true, false]]));
    $run = EvalFixtures::run('Refunds take five days[1]. Shipping is free[2].', blocks: ['Refunds take five days.', 'Shipping costs 5 EUR.']);

    expect(csScorer($provider)->score(csCase(['min_supported' => 0.5]), $run)->pass)->toBeTrue();
});

it('fails an answer that cites nothing without asking the judge', function (): void {
    $provider = CannedLlmProvider::answering('{}');

    $result = csScorer($provider)->score(csCase(), EvalFixtures::run('Refunds take five days.', blocks: ['Refunds take five days.']));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toBe('The answer cites no source.');
});

it('fails when the judge reply cannot be read', function (): void {
    $provider = CannedLlmProvider::answering(json_encode(['supported' => [true]]));

    $result = csScorer($provider)->score(csCase(), EvalFixtures::run('A[1]. B[1].', blocks: ['A. B.']));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toBe('Judge returned no parseable verdict.');
});
