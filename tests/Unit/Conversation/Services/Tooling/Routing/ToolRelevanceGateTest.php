<?php

use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Domain\Conversation\Services\Tooling\Routing\ToolRelevanceGate;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;

/**
 * Pure unit coverage for the gate's branching — no DB, no live API. The reranker
 * is mocked so each test pins an exact score distribution and asserts what ships.
 */
function toolEntry(string $name, string $description = '', bool $mutates = true): ToolEntry
{
    return new ToolEntry(
        kind: 'function',
        sourceId: 'function:'.$name,
        name: $name,
        description: $description,
        parameters: [],
        embedding: null,
        mutates: $mutates,
    );
}

function gateWith(Reranker $reranker): ToolRelevanceGate
{
    return new ToolRelevanceGate($reranker);
}

beforeEach(function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', true);
    config()->set('chatvia.agent.tools.routing.relevance_threshold', 0.55);
    // Pin both per-scope bars to a symmetric 0.55 baseline so the generic tests
    // below are deterministic regardless of the config-file defaults (read ships
    // at 0.45 in prod). The per-scope test overrides these.
    config()->set('chatvia.agent.tools.routing.relevance_threshold_read', 0.55);
    config()->set('chatvia.agent.tools.routing.relevance_threshold_write', 0.55);
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 12);
});

it('ships nothing when no candidate clears the threshold (the "Hi" case)', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.04],
        ['index' => 1, 'relevance_score' => 0.02],
    ]);

    $candidates = [
        toolEntry('get_weather', 'Get the weather forecast for a city'),
        toolEntry('create_event', 'Schedule a meeting on the calendar'),
    ];

    expect(gateWith($reranker)->gate('Hi', $candidates))->toBe([]);
});

it('keeps only the tools above the threshold, in reranked order', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    // Reranker promotes index 1 (weather) and drops index 0 (events) below the bar.
    $reranker->shouldReceive('rerank')->once()->andReturn([
        ['index' => 1, 'relevance_score' => 0.91],
        ['index' => 0, 'relevance_score' => 0.10],
    ]);

    $events = toolEntry('create_event', 'Schedule a meeting on the calendar');
    $weather = toolEntry('get_weather', 'Get the weather forecast for a city');

    $kept = gateWith($reranker)->gate("What's the weather in Copenhagen tomorrow?", [$events, $weather]);

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->name)->toBe('get_weather');
});

it('keeps multiple tools when several clear the threshold', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.88],
        ['index' => 1, 'relevance_score' => 0.72],
    ]);

    $commits = toolEntry('list_commits', 'List repository commits');
    $heatmap = toolEntry('render_heatmap', 'Render a heatmap chart');

    $kept = gateWith($reranker)->gate('fetch commits then render a heatmap', [$commits, $heatmap]);

    expect($kept)->toHaveCount(2)
        ->and($kept[0]->name)->toBe('list_commits')
        ->and($kept[1]->name)->toBe('render_heatmap');
});

it('holds write tools to a stricter bar than read tools', function (): void {
    // A wrong read is cheap, a wrong write executes — so split the bars.
    config()->set('chatvia.agent.tools.routing.relevance_threshold_read', 0.45);
    config()->set('chatvia.agent.tools.routing.relevance_threshold_write', 0.65);

    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    // Both land at 0.55 — between the read bar (0.45) and the write bar (0.65).
    $reranker->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.55],
        ['index' => 1, 'relevance_score' => 0.55],
    ]);

    $write = toolEntry('create_refund', 'Refund a charge to a customer', mutates: true);
    $read = toolEntry('get_charge', 'Retrieve a payment charge', mutates: false);

    $kept = gateWith($reranker)->gate('do something about that charge', [$write, $read]);

    // The read tool clears its looser bar; the borderline write tool is pruned.
    expect($kept)->toHaveCount(1)
        ->and($kept[0]->name)->toBe('get_charge');
});

it('treats the threshold as inclusive (>=)', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.55],
    ]);

    $kept = gateWith($reranker)->gate('exact boundary', [toolEntry('boundary_tool', 'right on the line')]);

    expect($kept)->toHaveCount(1);
});

it('passes candidates through unchanged when the reranker throws', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    $reranker->shouldReceive('rerank')->andThrow(new RuntimeException('reranker down'));

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($reranker)->gate('anything', $candidates))->toBe($candidates);
});

it('passes candidates through with the no-op reranker of an install without a reranking service', function (): void {
    $candidates = [
        toolEntry('get_weather', 'Get the weather forecast'),
        toolEntry('create_event', 'Schedule a meeting on the calendar'),
    ];

    expect(gateWith(new NullReranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('is a no-op passthrough when the gate is disabled', function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', false);

    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldNotReceive('rerank');

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($reranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('passes through when the reranker is disabled', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnFalse();
    $reranker->shouldNotReceive('rerank');

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($reranker)->gate('Hi', $candidates))->toBe($candidates);
});

it('returns [] for an empty candidate set without calling the reranker', function (): void {
    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldNotReceive('rerank');

    expect(gateWith($reranker)->gate('Hi', []))->toBe([]);
});

it('only reranks up to the configured candidate cap', function (): void {
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 2);

    $reranker = Mockery::mock(Reranker::class);
    $reranker->shouldReceive('isEnabled')->andReturnTrue();
    // Cap is 2, so only the first two candidates are sent to the reranker.
    $reranker->shouldReceive('rerank')
        ->once()
        ->with('anything', Mockery::on(fn (array $docs): bool => count($docs) === 2), 2)
        ->andReturn([
            ['index' => 0, 'relevance_score' => 0.90],
            ['index' => 1, 'relevance_score' => 0.80],
        ]);

    $candidates = [
        toolEntry('tool_a', 'a'),
        toolEntry('tool_b', 'b'),
        toolEntry('tool_c', 'c'),
    ];

    $kept = gateWith($reranker)->gate('anything', $candidates);

    expect($kept)->toHaveCount(2)
        ->and(array_map(static fn (ToolEntry $e): string => $e->name, $kept))->toBe(['tool_a', 'tool_b']);
});
