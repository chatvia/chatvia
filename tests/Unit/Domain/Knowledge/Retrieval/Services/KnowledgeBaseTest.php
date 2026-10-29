<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;
use Tests\Support\Knowledge\Embedding\FakeReranker;

/**
 * KnowledgeBase::search(): pages stored by ingestion come back closest in
 * meaning first, through the relevance check. A reranker failure is reported
 * and falls back to the basic similarity check, capped at max_chunks; without
 * a reranker the similarity check gates.
 */
beforeEach(function (): void {
    config([
        'chatvia.vector.search.relevance_check' => true,
        'chatvia.vector.search.rerank_candidates' => 12,
        'chatvia.vector.search.max_chunks' => 2,
    ]);
});

/**
 * An agent with $count ingested pages about the same thing. Every page and
 * the query get the same vector: similarity 1.0.
 */
function kbAgentWithPages(int $count): Agent
{
    app()->instance(EmbeddingsProvider::class, FakeEmbeddingsProvider::constant());

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    storeSourceChunks($source, array_map(fn (int $i): string => "Ocean fact number {$i}: the Pacific ocean is the largest ocean.", range(1, $count)));

    return $agent;
}

it('ranks the agent\'s ingested pages closest in meaning first, the similarity as their relevance', function (): void {
    $query = 'Where does energy come from?';
    $mitochondria = 'The mitochondria is the powerhouse of the cell.';
    $ribosomes = 'Ribosomes build proteins out of amino acids.';
    $chloroplasts = 'Chloroplasts perform photosynthesis in plant cells.';

    app()->instance(EmbeddingsProvider::class, FakeEmbeddingsProvider::directions([
        $query => [1.0, 0.0, 0.0],
        $mitochondria => [0.9, 0.4359, 0.0],
        $ribosomes => [0.6, 0.0, 0.8],
        $chloroplasts => [0.3, 0.9539, 0.0],
    ]));
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.vector.search.similarity_threshold' => 0.1, 'chatvia.vector.search.max_chunks' => 5]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    storeSourceChunks($source, [$chloroplasts, $mitochondria, $ribosomes]);

    $results = app(KnowledgeBase::class)->search($agent, $query);

    expect(array_map(fn (RetrievedChunkData $result): string => $result->chunk, $results))->toBe([$mitochondria, $ribosomes, $chloroplasts])
        ->and(array_map(fn (RetrievedChunkData $result): float => round((float) $result->similarity, 2), $results))->toBe([0.9, 0.6, 0.3])
        ->and(array_map(fn (RetrievedChunkData $result): ?float => $result->relevance, $results))
        ->toBe(array_map(fn (RetrievedChunkData $result): ?float => $result->similarity, $results));
});

it('reports a reranker failure and falls back to the similarity check, at most max_chunks results', function (): void {
    Queue::fake();
    Exceptions::fake();
    $agent = kbAgentWithPages(5);
    app()->instance(Reranker::class, FakeReranker::failing());

    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');
    config(['chatvia.vector.search.similarity_threshold' => 1.01]);
    $unreachable = app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($results)->not->toBeEmpty()
        ->and(count($results))->toBeLessThanOrEqual(2)
        ->and($unreachable)->toBe([]);

    Exceptions::assertReported(RuntimeException::class);
});

it('keeps only chunks close enough in meaning when no reranker is set up', function (float $threshold, int $expected): void {
    $agent = kbAgentWithPages(3);
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.vector.search.similarity_threshold' => $threshold]);

    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($results)->toHaveCount($expected)
        ->and(array_map(fn (RetrievedChunkData $result): ?float => $result->relevance, $results))
        ->toBe(array_map(fn (RetrievedChunkData $result): ?float => $result->similarity, $results));
})->with([
    'a reachable bar' => [0.5, 2],
    'an unreachable bar' => [1.01, 0],
]);
