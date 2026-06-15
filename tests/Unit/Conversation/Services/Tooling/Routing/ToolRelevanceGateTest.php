<?php

use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Domain\Conversation\Services\Tooling\Routing\ToolRelevanceGate;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;

/**
 * Pure unit coverage for the gate's branching — no DB, no live API. The reranker
 * is mocked so each test pins an exact score distribution and asserts what ships.
 */
function toolEntry(string $name, string $description = ''): ToolEntry
{
    return new ToolEntry(
        kind: 'function',
        sourceId: 'function:'.$name,
        name: $name,
        description: $description,
        parameters: [],
        embedding: null,
    );
}

function gateWith(EmbeddingsProviderInterface $provider): ToolRelevanceGate
{
    return new ToolRelevanceGate($provider);
}

beforeEach(function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', true);
    config()->set('chatvia.agent.tools.routing.relevance_threshold', 0.55);
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 12);
});

it('ships nothing when no candidate clears the threshold (the "Hi" case)', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    $provider->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.04],
        ['index' => 1, 'relevance_score' => 0.02],
    ]);

    $candidates = [
        toolEntry('get_weather', 'Get the weather forecast for a city'),
        toolEntry('create_event', 'Schedule a meeting on the calendar'),
    ];

    expect(gateWith($provider)->gate('Hi', $candidates))->toBe([]);
});

it('keeps only the tools above the threshold, in reranked order', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    // Reranker promotes index 1 (weather) and drops index 0 (events) below the bar.
    $provider->shouldReceive('rerank')->once()->andReturn([
        ['index' => 1, 'relevance_score' => 0.91],
        ['index' => 0, 'relevance_score' => 0.10],
    ]);

    $events = toolEntry('create_event', 'Schedule a meeting on the calendar');
    $weather = toolEntry('get_weather', 'Get the weather forecast for a city');

    $kept = gateWith($provider)->gate("What's the weather in Copenhagen tomorrow?", [$events, $weather]);

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->name)->toBe('get_weather');
});

it('keeps multiple tools when several clear the threshold', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    $provider->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.88],
        ['index' => 1, 'relevance_score' => 0.72],
    ]);

    $commits = toolEntry('list_commits', 'List repository commits');
    $heatmap = toolEntry('render_heatmap', 'Render a heatmap chart');

    $kept = gateWith($provider)->gate('fetch commits then render a heatmap', [$commits, $heatmap]);

    expect($kept)->toHaveCount(2)
        ->and($kept[0]->name)->toBe('list_commits')
        ->and($kept[1]->name)->toBe('render_heatmap');
});

it('treats the threshold as inclusive (>=)', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    $provider->shouldReceive('rerank')->once()->andReturn([
        ['index' => 0, 'relevance_score' => 0.55],
    ]);

    $kept = gateWith($provider)->gate('exact boundary', [toolEntry('boundary_tool', 'right on the line')]);

    expect($kept)->toHaveCount(1);
});

it('passes candidates through unchanged when the reranker throws', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    $provider->shouldReceive('rerank')->andThrow(new RuntimeException('reranker down'));

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($provider)->gate('anything', $candidates))->toBe($candidates);
});

it('passes candidates through when the reranker returns null', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    $provider->shouldReceive('rerank')->once()->andReturnNull();

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($provider)->gate('anything', $candidates))->toBe($candidates);
});

it('is a no-op passthrough when the gate is disabled', function (): void {
    config()->set('chatvia.agent.tools.routing.relevance_gate', false);

    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldNotReceive('rerank');

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($provider)->gate('Hi', $candidates))->toBe($candidates);
});

it('passes through when the provider has no reranker', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnFalse();
    $provider->shouldNotReceive('rerank');

    $candidates = [toolEntry('get_weather', 'Get the weather forecast')];

    expect(gateWith($provider)->gate('Hi', $candidates))->toBe($candidates);
});

it('returns [] for an empty candidate set without calling the reranker', function (): void {
    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldNotReceive('rerank');

    expect(gateWith($provider)->gate('Hi', []))->toBe([]);
});

it('only reranks up to the configured candidate cap', function (): void {
    config()->set('chatvia.agent.tools.routing.rerank_candidates', 2);

    $provider = Mockery::mock(EmbeddingsProviderInterface::class);
    $provider->shouldReceive('supportsReranking')->andReturnTrue();
    // Cap is 2, so only the first two candidates are sent to the reranker.
    $provider->shouldReceive('rerank')
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

    $kept = gateWith($provider)->gate('anything', $candidates);

    expect($kept)->toHaveCount(2)
        ->and(array_map(static fn (ToolEntry $e): string => $e->name, $kept))->toBe(['tool_a', 'tool_b']);
});
