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

/**
 * KnowledgeBase::search() behaviour around the reranker and the stats job:
 * a reranker failure is reported and falls back to the SQL ranking capped at
 * max_chunks (it used to return every candidate), and the stats job payload
 * carries only source references, never chunk text.
 */
function kbFallbackProvider(bool $rerankThrows): void
{
    app()->bind(EmbeddingsProviderInterface::class, fn (): EmbeddingsProviderInterface => new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $vector = array_fill(0, 1024, 0.1);

            return $returnAsText ? '['.implode(',', $vector).']' : $vector;
        }

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            return $this->embed($text, $returnAsText);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'fake-embed';
        }
    });

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
        'chatvia.vector.search.rerank' => true,
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

it('queues only source references for the retrieval stats job', function (): void {
    Queue::fake();
    kbFallbackProvider(rerankThrows: false);

    $agent = kbFallbackAgentWithChunks(3);

    app(KnowledgeBase::class)->search($agent, 'largest ocean');

    Queue::assertPushed(UpdateRetrievalStatsJob::class, function (UpdateRetrievalStatsJob $job) use ($agent): bool {
        expect($job->agentId)->toBe($agent->id)
            ->and($job->results)->toHaveCount(1); // three chunks, one source

        foreach ($job->results as $ref) {
            expect($ref)->toBeArray()
                ->and(array_keys($ref))->toBe(['chunkable_type', 'chunkable_id'])
                ->and($ref['chunkable_type'])->toBe('source_file');
        }

        return true;
    });
});
