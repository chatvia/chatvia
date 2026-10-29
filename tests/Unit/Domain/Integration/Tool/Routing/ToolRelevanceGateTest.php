<?php

declare(strict_types=1);

use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Routing\ToolRelevanceGate;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Rerank\NullReranker;

/**
 * The relevance gate keeps only the tools a question needs: those the
 * reranker scores at or over the read or the stricter write bar (a wrong read
 * is cheap, a wrong write executes), in reranked order, or, without a working
 * reranker, those close enough by vector similarity; with no bar known for
 * the embedding model the pool passes ungated. The reranker is mocked so each
 * test pins an exact score distribution and asserts what ships.
 */
function relevanceGateEntry(string $name, string $description = '', bool $mutates = true): ToolEntryData
{
    return new ToolEntryData(
        kind: ToolKind::Function,
        sourceId: 'function:'.$name,
        name: $name,
        description: $description,
        parameters: [],
        mutates: $mutates,
    );
}

function relevanceGateWith(Reranker $reranker): ToolRelevanceGate
{
    return new ToolRelevanceGate($reranker);
}

/**
 * A working reranker that scores the candidates once, as given: index => score.
 *
 * @param  array<int, float>  $scores
 */
function relevanceGateScores(array $scores): Reranker
{
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('model')->andReturn('test-reranker');
    $reranker->shouldReceive('rerank')->once()->andReturn(array_map(
        static fn (int $index, float $score): RerankedDocumentData => new RerankedDocumentData($index, $score),
        array_keys($scores),
        $scores,
    ));

    return $reranker;
}

/** A working reranker whose service fails. */
function relevanceGateFailing(): Reranker
{
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('model')->andReturn('test-reranker');
    $reranker->shouldReceive('rerank')->andThrow(new RuntimeException('reranker down'));

    return $reranker;
}

beforeEach(function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', true);
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 12);
});

it('keeps only the tools at or over their scope\'s reranker bar, in reranked order', function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_threshold_read', 0.45);
    config()->set('chatvia.agent.tools.routing.relevance_threshold_write', 0.65);
    $events = relevanceGateEntry('create_event', 'Schedule a meeting on the calendar', mutates: true);
    $weather = relevanceGateEntry('get_weather', 'Get the weather forecast for a city', mutates: false);
    $refund = relevanceGateEntry('create_refund', 'Refund a charge to a customer', mutates: true);
    $charge = relevanceGateEntry('get_charge', 'Retrieve a payment charge', mutates: false);
    $reranker = relevanceGateScores([1 => 0.91, 2 => 0.55, 3 => 0.45, 0 => 0.10]);

    $kept = relevanceGateWith($reranker)->gate('the weather, and that charge', [$events, $weather, $refund, $charge]);

    expect(array_map(static fn (ToolEntryData $e): string => $e->name, $kept))->toBe(['get_weather', 'get_charge']);
});

it('falls back to vector similarity without a working reranker, passing the pool when no bar is known', function (Closure $reranker, array $similarity, array $kept): void {
    $read = relevanceGateEntry('get_charge', 'Look up a charge', mutates: false);
    $write = relevanceGateEntry('refund_charge', 'Refund a charge', mutates: true);
    $far = relevanceGateEntry('get_weather', 'Get the weather', mutates: false);

    $result = relevanceGateWith($reranker())->gate('refund that charge', [$read, $write, $far], [
        $read->sourceId => $similarity[0],
        $write->sourceId => $similarity[1],
        $far->sourceId => $similarity[2],
    ]);

    expect(array_map(static fn (ToolEntryData $e): string => $e->name, $result))->toBe($kept);
})->with([
    'the reranker fails: the admin\'s similarity bars' => [function (): Reranker {
        config()->set('chatvia.agent.tools.routing.similarity_threshold_read', 0.5);
        config()->set('chatvia.agent.tools.routing.similarity_threshold_write', 0.6);

        return relevanceGateFailing();
    }, [0.55, 0.58, 0.12], ['get_charge']],
    'no reranker: the embedding model\'s bars' => [function (): Reranker {
        config()->set('chatvia.ai.models.embedding', 'qwen');
        config()->set('chatvia.agent.tools.routing.similarity_threshold_read');
        config()->set('chatvia.agent.tools.routing.similarity_threshold_write');

        return new NullReranker;
    }, [0.9, 0.01, 0.01], ['get_charge']],
    'no reranker and a model never measured: ungated' => [function (): Reranker {
        config()->set('chatvia.ai.models.embedding', 'openai');

        return new NullReranker;
    }, [0.01, 0.01, 0.01], ['get_charge', 'refund_charge', 'get_weather']],
]);
