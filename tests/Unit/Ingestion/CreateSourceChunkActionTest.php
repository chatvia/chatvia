<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;

/**
 * Behavioural coverage for the chunk-creation unit at the heart of the RAG
 * pipeline (was on the untested allowlist — see review H5). Asserts a chunk is
 * persisted with the right content/index/checksum/owner, and that a re-run with
 * the same checksum is idempotent (returns null, creates no duplicate).
 *
 * REQUIRES the migrated test DB: chunks uses pgvector vector(1024) +
 * bm25vector (the test connection needs the pgvector + bm25 extensions). The
 * embedding provider is faked here so no external call is made; the bm25
 * expression is computed locally by the action.
 */
beforeEach(function (): void {
    // embed() resolves EmbeddingsProviderInterface from the container, so a
    // single container binding covers both the action's injected dependency and
    // the embed() helper it calls. Return a 1024-dim zero vector to match the
    // chunks.embedding column dimension.
    $this->app->bind(EmbeddingsProviderInterface::class, fn (): EmbeddingsProviderInterface => new class implements EmbeddingsProviderInterface
    {
        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $vector = array_fill(0, 1024, 0.0);

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
            return 'fake-embed-model';
        }
    });
});

it('persists a chunk for a source with content, index, checksum and owner', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create();

    $page = new PageData(
        pageNumber: 3,
        content: 'The mitochondria is the powerhouse of the cell.',
        checksum: 'checksum-aaa',
    );

    $chunk = app(CreateSourceChunkAction::class)->execute($agent, $source, $page);

    expect($chunk)->toBeInstanceOf(Chunk::class)
        ->and($chunk->chunk)->toBe('The mitochondria is the powerhouse of the cell.')
        ->and($chunk->chunk_index)->toBe(3)
        ->and($chunk->checksum)->toBe('checksum-aaa')
        ->and($chunk->user_id)->toBe($agent->user_id)
        ->and($chunk->chunkable_type)->toBe((new SourceFile)->getMorphClass())
        ->and($chunk->chunkable_id)->toBe($source->getKey());
});

it('stores chunk/summary/questions as plaintext at rest', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create();

    $page = new PageData(
        pageNumber: 1,
        content: 'The mitochondria is the powerhouse of the cell.',
        checksum: 'checksum-enc',
    );

    $chunk = app(CreateSourceChunkAction::class)->execute($agent, $source, $page);
    $chunk->update(['summary' => 'A plain summary.', 'questions' => "Who?\nWhat?"]);

    // Raw column values are stored as plaintext — no encryption cast.
    $raw = DB::table('chunks')->where('id', $chunk->id)->first();

    expect($raw->chunk)->toBe('The mitochondria is the powerhouse of the cell.')
        ->and($raw->summary)->toBe('A plain summary.')
        ->and($raw->questions)->toBe("Who?\nWhat?");
});

it('is idempotent — a re-run with the same checksum returns null and creates no duplicate', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create();

    $page = new PageData(pageNumber: 1, content: 'Repeated content.', checksum: 'dupe-checksum');

    $first = app(CreateSourceChunkAction::class)->execute($agent, $source, $page);
    $second = app(CreateSourceChunkAction::class)->execute($agent, $source, $page);

    expect($first)->toBeInstanceOf(Chunk::class)
        ->and($second)->toBeNull()
        ->and(Chunk::query()->where('checksum', 'dupe-checksum')->count())->toBe(1);
});
