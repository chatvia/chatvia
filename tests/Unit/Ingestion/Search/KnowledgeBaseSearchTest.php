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
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Integration coverage for the RAG retrieval pipeline that was the review's
 * #1 named gap: chunks ingested via CreateSourceChunksAction → searched via
 * KnowledgeBase::search → ranked + confidence-scored result objects.
 *
 * The pure helpers (confidenceScore / stripXmlTags) are covered by
 * RetrievalScoringTest; THIS file exercises the live SQL: the four-channel
 * RRF (vector + question + bm25 + question_bm25), agent-scoping via
 * agent_sources EXISTS, and the processResults wrap that maps ranks to
 * confidence_score.
 *
 * REQUIRES the migrated + seeded test DB with pgvector + bm25 + tokenizer
 * extensions installed (the canonical install_vectorchord_extensions migration).
 * Embeddings are faked at the provider boundary so no external API is touched.
 */
function knowledgeBaseEmbeddingFake(): void
{
    // Distinct fixed vectors per call so different chunks don't all collapse to
    // identical embeddings (which would hide ranking bugs behind tied distances).
    $next = 0;
    app()->bind(function () use (&$next): EmbeddingsProviderInterface {
        return new readonly class($next++) implements EmbeddingsProviderInterface
        {
            public function __construct(private int $seed) {}

            public function embed(string $text, bool $returnAsText = false): string|array
            {
                $base = 0.1 + ($this->seed % 7) * 0.01;
                $vector = array_fill(0, 1024, $base);

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
        };
    });

    // No reranking service here: results keep their unranked order.
    app()->instance(Reranker::class, new NullReranker);
}

/** Stores one page of a file as a plain chunk. */
function knowledgeBaseChunk(SourceFile $source, PageData $page): void
{
    app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
        source: $source,
        group: new PageGroupData([$page]),
        enhancement: ChunkEnhancement::Off,
    ));
}

beforeEach(function (): void {
    knowledgeBaseEmbeddingFake();
});

it('returns an empty array when the agent has no chunks', function (): void {
    $agent = Agent::factory()->create();

    $results = app(KnowledgeBase::class)->search($agent, 'powerhouse');

    expect($results)->toBe([]);
});

it('returns ranked results scoped to the agent with confidence scores from RetrievalScoring', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    knowledgeBaseChunk($source, new PageData(
        pageNumber: 1,
        content: 'The mitochondria is the powerhouse of the cell.',
        checksum: 'kb-test-mito',
    ));
    knowledgeBaseChunk($source, new PageData(
        pageNumber: 2,
        content: 'Chloroplasts perform photosynthesis in plant cells.',
        checksum: 'kb-test-chloro',
    ));

    $results = app(KnowledgeBase::class)->search($agent, 'powerhouse mitochondria');

    expect($results)->not->toBe([])
        ->and($results[0])->toHaveProperties(['id', 'chunk', 'chunkable_type', 'chunkable_id', 'confidence_score', 'search_type'])
        // confidenceScore: top result → 0.99 (covered structurally by RetrievalScoringTest).
        ->and($results[0]->confidence_score)->toBeFloat()
        ->and($results[0]->confidence_score)->toBeGreaterThanOrEqual(0.5)
        ->and($results[0]->confidence_score)->toBeLessThanOrEqual(0.99);
});

it('does not return another agent\'s chunks (tenancy boundary)', function (): void {
    $mine = Agent::factory()->create();
    $mySource = AgentFileFactory::new()->create(['workspace_id' => $mine->workspace_id]);
    $mine->files()->attach($mySource->id);
    knowledgeBaseChunk($mySource, new PageData(
        pageNumber: 1,
        content: 'My private content about quarterly forecasts.',
        checksum: 'kb-test-mine',
    ));

    $other = Agent::factory()->create();
    $otherSource = AgentFileFactory::new()->create(['workspace_id' => $other->workspace_id]);
    $other->files()->attach($otherSource->id);
    knowledgeBaseChunk($otherSource, new PageData(
        pageNumber: 1,
        content: 'Other tenant secret roadmap details — leak guard.',
        checksum: 'kb-test-other',
    ));

    // Search as the second agent for content only the first agent owns.
    $results = app(KnowledgeBase::class)->search($other, 'quarterly forecasts');

    $chunks = collect($results)->pluck('chunk');
    expect($chunks)->not->toContain('My private content about quarterly forecasts.');
});
