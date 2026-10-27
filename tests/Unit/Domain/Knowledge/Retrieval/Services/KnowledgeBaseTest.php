<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Spectra\Spectra;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;
use Tests\Support\Knowledge\Embedding\FakeReranker;

/**
 * KnowledgeBase::search(): pages stored by ingestion come back closest in
 * meaning first, through the relevance check. A reranker failure is reported
 * and falls back to the basic similarity check, capped at max_chunks; without
 * a reranker the similarity check gates; the results fit the context budget;
 * an agent without chunks costs no embedding call; its calls are tagged
 * `knowledge-search` in Spectra; and counting the retrieval is left to the
 * turn.
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
    // Vectors placed by hand (each text maps to a direction, so the
    // similarities are known), and the query shares no word with any page, so
    // the order is the vector order.
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
    // A bar every page reaches, so only the order is under test.
    config(['chatvia.vector.search.similarity_threshold' => 0.1, 'chatvia.vector.search.max_chunks' => 5]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    // Stored in another order than they rank.
    storeSourceChunks($source, [$chloroplasts, $mitochondria, $ribosomes]);

    $results = app(KnowledgeBase::class)->search($agent, $query);

    expect(array_map(fn (RetrievedChunkData $result): string => $result->chunk, $results))->toBe([$mitochondria, $ribosomes, $chloroplasts])
        ->and(array_map(fn (RetrievedChunkData $result): float => round((float) $result->similarity, 2), $results))->toBe([0.9, 0.6, 0.3])
        ->and(array_map(fn (RetrievedChunkData $result): ?float => $result->relevance, $results))
        ->toBe(array_map(fn (RetrievedChunkData $result): ?float => $result->similarity, $results));
});

it('skips the query embedding when the agent reaches no chunks', function (): void {
    $agent = Agent::factory()->create();
    $agent->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id])->id);

    $embeddings = Mockery::mock(EmbeddingsProvider::class);
    $embeddings->shouldNotReceive('embedQuery');
    app()->instance(EmbeddingsProvider::class, $embeddings);

    expect(app(KnowledgeBase::class)->search($agent, 'anything'))->toBe([]);
});

it('reports a reranker failure and returns at most max_chunks results', function (): void {
    Queue::fake();
    Exceptions::fake();
    $agent = kbAgentWithPages(5);
    app()->instance(Reranker::class, FakeReranker::failing());

    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($results)->not->toBeEmpty()
        ->and(count($results))->toBeLessThanOrEqual(2);

    Exceptions::assertReported(RuntimeException::class);
});

it('tags its rerank as a knowledge search in Spectra', function (): void {
    Queue::fake();
    config(['spectra.enabled' => true]);
    $agent = kbAgentWithPages(3);

    $reranker = new class extends FakeReranker
    {
        /** @var list<string> */
        public array $tags = [];

        public function rerank(string $query, array $documents, ?int $topN = null): array
        {
            $this->tags = app(Spectra::class)->startRequest('cohere', 'rerank-test')->tags;

            return parent::rerank($query, $documents, $topN);
        }
    };
    app()->instance(Reranker::class, $reranker);

    app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($reranker->tags)->toBe(['knowledge-search']);
});

it('leaves counting the retrieval to the turn that uses the results', function (): void {
    Queue::fake();
    $agent = kbAgentWithPages(3);

    app(KnowledgeBase::class)->search($agent, 'largest ocean');

    Queue::assertNotPushed(UpdateRetrievalStatsJob::class);
});

it('returns as many results as asked for, whatever the reranker\'s own default cap', function (): void {
    Queue::fake();
    config(['chatvia.vector.search.max_chunks' => 4, 'chatvia.vector.search.relevance_threshold' => null]);
    $agent = kbAgentWithPages(6);
    app()->instance(Reranker::class, FakeReranker::cappedAt(3));

    expect(app(KnowledgeBase::class)->search($agent, 'largest ocean'))->toHaveCount(4);
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

it('falls back to the similarity check when the reranker fails', function (): void {
    Exceptions::fake();
    config(['chatvia.vector.search.similarity_threshold' => 1.01]);
    $agent = kbAgentWithPages(3);
    app()->instance(Reranker::class, FakeReranker::failing());

    expect(app(KnowledgeBase::class)->search($agent, 'largest ocean'))->toBe([]);

    Exceptions::assertReported(RuntimeException::class);
});

it('keeps the results within the context budget, the best always in', function (int $budget, int $expected): void {
    config(['chatvia.vector.search.max_chunks' => 3, 'chatvia.vector.search.max_context_tokens' => $budget]);
    $agent = kbAgentWithPages(3);

    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');
    $tokens = $results === [] ? 0 : $results[0]->tokens;

    expect($tokens)->toBeGreaterThan(0)
        ->and($results)->toHaveCount($expected);
})->with([
    'room for all' => [10_000, 3],
    'room for two' => [32, 2],
    'too small even for one' => [1, 1],
]);
