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
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * From ingestion to search: pages stored by CreateSourceChunksAction come back
 * from KnowledgeBase::search() closest in meaning first. Vectors are placed by
 * hand (each text maps to a direction, so the similarities are known), and
 * the query shares no word with any page, so the order is the vector order.
 * Without a reranker, the basic similarity check gives each result its
 * relevance.
 */
function knowledgeBaseChunk(SourceFile $source, int $pageNumber, string $content): void
{
    app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
        source: $source,
        group: new PageGroupData([new PageData(pageNumber: $pageNumber, content: $content, checksum: md5($content))]),
        enhancement: ChunkEnhancement::Off,
    ));
}

it('ranks the agent\'s ingested pages closest in meaning first, the similarity as their relevance', function (): void {
    $query = 'Where does energy come from?';
    $mitochondria = 'The mitochondria is the powerhouse of the cell.';
    $ribosomes = 'Ribosomes build proteins out of amino acids.';
    $chloroplasts = 'Chloroplasts perform photosynthesis in plant cells.';

    app()->instance(EmbeddingsProviderInterface::class, FakeEmbeddingsProvider::directions([
        $query => [1.0, 0.0, 0.0],
        $mitochondria => [0.9, 0.4359, 0.0],
        $ribosomes => [0.6, 0.0, 0.8],
        $chloroplasts => [0.3, 0.9539, 0.0],
    ]));
    app()->instance(Reranker::class, new NullReranker);
    // A bar every page reaches, so only the order is under test.
    config(['chatvia.vector.search.similarity_threshold' => 0.1]);

    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($source->id);

    // Stored in another order than they rank.
    knowledgeBaseChunk($source, 1, $chloroplasts);
    knowledgeBaseChunk($source, 2, $mitochondria);
    knowledgeBaseChunk($source, 3, $ribosomes);

    $results = app(KnowledgeBase::class)->search($agent, $query);

    expect(array_map(fn (RetrievedChunkData $result): string => $result->chunk, $results))->toBe([$mitochondria, $ribosomes, $chloroplasts])
        ->and(array_map(fn (RetrievedChunkData $result): float => round((float) $result->similarity, 2), $results))->toBe([0.9, 0.6, 0.3])
        ->and(array_map(fn (RetrievedChunkData $result): ?float => $result->relevance, $results))
        ->toBe(array_map(fn (RetrievedChunkData $result): ?float => $result->similarity, $results));
});
