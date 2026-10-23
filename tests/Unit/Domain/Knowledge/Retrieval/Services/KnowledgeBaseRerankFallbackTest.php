<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * KnowledgeBase::search() behaviour around the relevance check: a reranker
 * failure is reported and falls back to the basic similarity check, capped at
 * max_chunks; without a reranker the similarity check gates; the results fit
 * the context budget; and counting the retrieval is left to the turn.
 */
function kbFallbackProvider(bool $rerankThrows): void
{
    // Every chunk and the query get the same vector: similarity 1.0.
    app()->instance(EmbeddingsProviderInterface::class, FakeEmbeddingsProvider::constant());

    app()->instance(Reranker::class, new readonly class($rerankThrows) implements Reranker
    {
        public function __construct(private bool $rerankThrows) {}

        public function rerank(string $query, array $documents, ?int $topN = null): array
        {
            if ($this->rerankThrows) {
                throw new RuntimeException('reranker down');
            }

            return (new NullReranker)->rerank($query, $documents, $topN);
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function model(): string
        {
            return 'fake-reranker';
        }
    });
}

function kbFallbackAgentWithChunks(int $count): Agent
{
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
        source: $source,
        group: new PageGroupData(array_map(fn (int $i): PageData => new PageData(
            pageNumber: $i,
            content: "Ocean fact number {$i}: the Pacific ocean is the largest ocean.",
            checksum: 'kb-fallback-'.$i.'-'.uniqid(),
        ), range(1, $count))),
        enhancement: ChunkEnhancement::Off,
    ));

    return $agent;
}

beforeEach(function (): void {
    config([
        'chatvia.vector.search.relevance_check' => true,
        'chatvia.vector.search.rerank_candidates' => 12,
        'chatvia.vector.search.max_chunks' => 2,
    ]);
});

it('reports a reranker failure and returns at most max_chunks results', function (): void {
    Queue::fake();
    Exceptions::fake();
    kbFallbackProvider(rerankThrows: true);

    $agent = kbFallbackAgentWithChunks(5);

    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($results)->not->toBeEmpty()
        ->and(count($results))->toBeLessThanOrEqual(2);

    Exceptions::assertReported(RuntimeException::class);
});

it('leaves counting the retrieval to the turn that uses the results', function (): void {
    Queue::fake();
    kbFallbackProvider(rerankThrows: false);

    $agent = kbFallbackAgentWithChunks(3);

    app(KnowledgeBase::class)->search($agent, 'largest ocean');

    Queue::assertNotPushed(UpdateRetrievalStatsJob::class);
});

it('returns as many results as asked for, whatever the reranker\'s own default cap', function (): void {
    Queue::fake();
    kbFallbackProvider(rerankThrows: false);
    config(['chatvia.vector.search.max_chunks' => 4, 'chatvia.vector.search.relevance_threshold' => null]);

    // Like Qwen3's endpoint: without a top_n it returns only 3 documents.
    app()->instance(Reranker::class, new class implements Reranker
    {
        public function rerank(string $query, array $documents, ?int $topN = null): array
        {
            return array_slice((new NullReranker)->rerank($query, $documents), 0, $topN ?? 3);
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function model(): string
        {
            return 'fake-reranker';
        }
    });

    $results = app(KnowledgeBase::class)->search(kbFallbackAgentWithChunks(6), 'largest ocean');

    expect($results)->toHaveCount(4);
});

it('keeps only chunks close enough in meaning when no reranker is set up', function (float $threshold, int $expected): void {
    kbFallbackProvider(rerankThrows: false);
    app()->instance(Reranker::class, new NullReranker);
    config(['chatvia.vector.search.similarity_threshold' => $threshold]);

    $agent = kbFallbackAgentWithChunks(3);

    // Every chunk and the query share one vector here: similarity 1.0.
    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');

    expect($results)->toHaveCount($expected);

    foreach ($results as $result) {
        expect($result->relevance)->toBe($result->similarity);
    }
})->with([
    'a reachable bar' => [0.5, 2],
    'an unreachable bar' => [1.01, 0],
]);

it('falls back to the similarity check when the reranker fails', function (): void {
    Exceptions::fake();
    kbFallbackProvider(rerankThrows: true);
    config(['chatvia.vector.search.similarity_threshold' => 1.01]);

    $agent = kbFallbackAgentWithChunks(3);

    expect(app(KnowledgeBase::class)->search($agent, 'largest ocean'))->toBe([]);

    Exceptions::assertReported(RuntimeException::class);
});

it('keeps the results within the context budget, the best always in', function (int $budget, int $expected): void {
    kbFallbackProvider(rerankThrows: false);
    config(['chatvia.vector.search.max_chunks' => 3, 'chatvia.vector.search.max_context_tokens' => $budget]);

    $agent = kbFallbackAgentWithChunks(3);
    $results = app(KnowledgeBase::class)->search($agent, 'largest ocean');
    $tokens = $results === [] ? 0 : $results[0]->tokens;

    expect($tokens)->toBeGreaterThan(0)
        ->and($results)->toHaveCount($expected);
})->with([
    'room for all' => [10_000, 3],
    'room for two' => [32, 2],
    'too small even for one' => [1, 1],
]);
