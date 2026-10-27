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
 * reranker scores over the read or the stricter write bar, or, without a
 * working reranker, those close enough by vector similarity. The reranker is
 * mocked so each test pins an exact score distribution and asserts what ships.
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
    // Pin both per-scope bars to a symmetric 0.55 baseline so the generic tests
    // below are deterministic regardless of the model's measured bars. The
    // per-scope test overrides these.
    config()->set('chatvia.agent.tools.routing.relevance_threshold_read', 0.55);
    config()->set('chatvia.agent.tools.routing.relevance_threshold_write', 0.55);
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 12);
});

it('ships nothing when no candidate clears the threshold (the "Hi" case)', function (): void {
    $reranker = relevanceGateScores([0 => 0.04, 1 => 0.02]);

    $candidates = [
        relevanceGateEntry('get_weather', 'Get the weather forecast for a city'),
        relevanceGateEntry('create_event', 'Schedule a meeting on the calendar'),
    ];

    expect(relevanceGateWith($reranker)->gate('Hi', $candidates))->toBe([]);
});

it('keeps only the tools above the threshold, in reranked order', function (): void {
    // Reranker promotes index 1 (weather) and drops index 0 (events) below the bar.
    $reranker = relevanceGateScores([1 => 0.91, 0 => 0.10]);

    $events = relevanceGateEntry('create_event', 'Schedule a meeting on the calendar');
    $weather = relevanceGateEntry('get_weather', 'Get the weather forecast for a city');

    $kept = relevanceGateWith($reranker)->gate("What's the weather in Copenhagen tomorrow?", [$events, $weather]);

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->name)->toBe('get_weather');
});

it('keeps multiple tools when several clear the threshold', function (): void {
    $reranker = relevanceGateScores([0 => 0.88, 1 => 0.72]);

    $commits = relevanceGateEntry('list_commits', 'List repository commits');
    $heatmap = relevanceGateEntry('render_heatmap', 'Render a heatmap chart');

    $kept = relevanceGateWith($reranker)->gate('fetch commits then render a heatmap', [$commits, $heatmap]);

    expect($kept)->toHaveCount(2)
        ->and($kept[0]->name)->toBe('list_commits')
        ->and($kept[1]->name)->toBe('render_heatmap');
});

it('holds write tools to a stricter bar than read tools', function (): void {
    // A wrong read is cheap, a wrong write executes — so split the bars.
    config()->set('chatvia.agent.tools.routing.relevance_threshold_read', 0.45);
    config()->set('chatvia.agent.tools.routing.relevance_threshold_write', 0.65);

    // Both land at 0.55 — between the read bar (0.45) and the write bar (0.65).
    $reranker = relevanceGateScores([0 => 0.55, 1 => 0.55]);

    $write = relevanceGateEntry('create_refund', 'Refund a charge to a customer', mutates: true);
    $read = relevanceGateEntry('get_charge', 'Retrieve a payment charge', mutates: false);

    $kept = relevanceGateWith($reranker)->gate('do something about that charge', [$write, $read]);

    // The read tool clears its looser bar; the borderline write tool is pruned.
    expect($kept)->toHaveCount(1)
        ->and($kept[0]->name)->toBe('get_charge');
});

it('treats the threshold as inclusive (>=)', function (): void {
    $reranker = relevanceGateScores([0 => 0.55]);

    $kept = relevanceGateWith($reranker)->gate('exact boundary', [relevanceGateEntry('boundary_tool', 'right on the line')]);

    expect($kept)->toHaveCount(1);
});

it('passes candidates through unchanged when the reranker throws', function (): void {
    $reranker = relevanceGateFailing();

    $candidates = [relevanceGateEntry('get_weather', 'Get the weather forecast')];

    expect(relevanceGateWith($reranker)->gate('anything', $candidates))->toBe($candidates);
});

it('passes candidates through with the no-op reranker of an install without a reranking service', function (): void {
    $candidates = [
        relevanceGateEntry('get_weather', 'Get the weather forecast'),
        relevanceGateEntry('create_event', 'Schedule a meeting on the calendar'),
    ];

    expect(relevanceGateWith(new NullReranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('is a no-op passthrough when the gate is disabled', function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', false);

    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldNotReceive('rerank');

    $candidates = [relevanceGateEntry('get_weather', 'Get the weather forecast')];

    expect(relevanceGateWith($reranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('passes through when the reranker is disabled', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnFalse();
    $reranker->shouldNotReceive('rerank');

    $candidates = [relevanceGateEntry('get_weather', 'Get the weather forecast')];

    expect(relevanceGateWith($reranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('returns [] for an empty candidate set without calling the reranker', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldNotReceive('rerank');

    expect(relevanceGateWith($reranker)->gate('Hi', []))->toBe([]);
});

it('only reranks up to the configured candidate cap', function (): void {
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 2);

    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('model')->andReturn('test-reranker');
    // Cap is 2, so only the first two candidates are sent to the reranker.
    $reranker->shouldReceive('rerank')
        ->once()
        ->with('anything', Mockery::on(fn (array $docs): bool => count($docs) === 2), 2)
        ->andReturn([
            new RerankedDocumentData(0, 0.90),
            new RerankedDocumentData(1, 0.80),
        ]);

    $candidates = [
        relevanceGateEntry('tool_a', 'a'),
        relevanceGateEntry('tool_b', 'b'),
        relevanceGateEntry('tool_c', 'c'),
    ];

    $kept = relevanceGateWith($reranker)->gate('anything', $candidates);

    expect($kept)->toHaveCount(2)
        ->and(array_map(static fn (ToolEntryData $e): string => $e->name, $kept))->toBe(['tool_a', 'tool_b']);
});

it('falls back to the similarity check when the reranker fails', function (): void {
    config()->set('chatvia.agent.tools.routing.similarity_threshold_read', 0.5);
    config()->set('chatvia.agent.tools.routing.similarity_threshold_write', 0.6);

    $reranker = relevanceGateFailing();

    $read = relevanceGateEntry('get_charge', 'Look up a charge', mutates: false);
    $write = relevanceGateEntry('refund_charge', 'Refund a charge', mutates: true);
    $far = relevanceGateEntry('get_weather', 'Get the weather', mutates: false);

    $kept = relevanceGateWith($reranker)->gate('refund that charge', [$read, $write, $far], [
        $read->sourceId => 0.55,
        $write->sourceId => 0.58,
        $far->sourceId => 0.12,
    ]);

    // The write misses its stricter bar; the unrelated read misses the read bar.
    expect(array_map(static fn (ToolEntryData $e): string => $e->name, $kept))->toBe(['get_charge']);
});

it('gates by similarity without a reranker, with the embedding model\'s bars', function (): void {
    config()->set('chatvia.ai.models.embedding', 'qwen');
    config()->set('chatvia.agent.tools.routing.similarity_threshold_read');
    config()->set('chatvia.agent.tools.routing.similarity_threshold_write');

    $near = relevanceGateEntry('get_weather', 'Get the weather', mutates: false);
    $far = relevanceGateEntry('create_event', 'Schedule a meeting', mutates: false);

    $kept = relevanceGateWith(new NullReranker)->gate('Hi', [$near, $far], [$near->sourceId => 0.9, $far->sourceId => 0.01]);

    expect(array_map(static fn (ToolEntryData $e): string => $e->name, $kept))->toBe(['get_weather']);
});

it('passes the pool ungated when the embedding model was never measured', function (): void {
    config()->set('chatvia.ai.models.embedding', 'openai');

    $candidates = [relevanceGateEntry('get_weather', 'Get the weather', mutates: false)];

    expect(relevanceGateWith(new NullReranker)->gate('Hi', $candidates, [$candidates[0]->sourceId => 0.01]))->toBe($candidates);
});
