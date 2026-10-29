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
 * unsupported. An answer that cites nothing fails without asking the judge.
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

    expect($result->score)->toBe(0.3333)
        ->and($result->pass)->toBeFalse();
});

it('fails an answer that cites nothing without asking the judge', function (): void {
    $provider = CannedLlmProvider::answering('{}');

    $result = csScorer($provider)->score(csCase(), EvalFixtures::run('Refunds take five days.', blocks: ['Refunds take five days.']));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toBe('The answer cites no source.');
});
