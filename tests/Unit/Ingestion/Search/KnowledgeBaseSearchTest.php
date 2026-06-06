<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Services\Search\KnowledgeBase;

/**
 * Integration coverage for the RAG retrieval pipeline that was the review's
 * #1 named gap: chunks ingested via CreateSourceChunkAction → searched via
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
    app()->bind(EmbeddingsProviderInterface::class, function () use (&$next): EmbeddingsProviderInterface {
        return new class($next++) implements EmbeddingsProviderInterface
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

            public function rerank(string $query, array $documents): ?array
            {
                return null;
            }

            public function supportsReranking(): bool
            {
                return false;
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
}

beforeEach(function (): void {
    // Array cache avoids needing a Redis server for the embed:query: memo.
    config(['vector.cache.store' => 'array']);
    knowledgeBaseEmbeddingFake();
});

it('returns an empty array when the agent has no chunks', function (): void {
    $agent = Agent::factory()->create();

    $results = app(KnowledgeBase::class)->search($agent, 'powerhouse');

    expect($results)->toBe([]);
});

it('returns ranked results scoped to the agent with confidence scores from RetrievalScoring', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create();
    $agent->files()->attach($source->id);

    $createChunk = app(CreateSourceChunkAction::class);
    $createChunk->execute($agent, $source, new PageData(
        pageNumber: 1,
        content: 'The mitochondria is the powerhouse of the cell.',
        checksum: 'kb-test-mito',
    ));
    $createChunk->execute($agent, $source, new PageData(
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
    $mySource = AgentFileFactory::new()->create();
    $mine->files()->attach($mySource->id);
    app(CreateSourceChunkAction::class)->execute($mine, $mySource, new PageData(
        pageNumber: 1,
        content: 'My private content about quarterly forecasts.',
        checksum: 'kb-test-mine',
    ));

    $other = Agent::factory()->create();
    $otherSource = AgentFileFactory::new()->create();
    $other->files()->attach($otherSource->id);
    app(CreateSourceChunkAction::class)->execute($other, $otherSource, new PageData(
        pageNumber: 1,
        content: 'Other tenant secret roadmap details — leak guard.',
        checksum: 'kb-test-other',
    ));

    // Search as the second agent for content only the first agent owns.
    $results = app(KnowledgeBase::class)->search($other, 'quarterly forecasts');

    $chunks = collect($results)->pluck('chunk');
    expect($chunks)->not->toContain('My private content about quarterly forecasts.');
});
