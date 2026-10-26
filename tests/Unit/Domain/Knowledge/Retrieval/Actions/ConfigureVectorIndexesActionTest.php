<?php

declare(strict_types=1);

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Actions\ConfigureVectorIndexesAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The action builds the chunk HNSW indexes in the kind EMBEDDING_INDEX names,
 * under their fixed names, and leaves an index already in that kind alone.
 * DDL here runs inside the test transaction, so each test leaves the database
 * as it was.
 */
function cviaRun(string $kind): Collection
{
    config(['chatvia.embeddings.index' => $kind]);

    return app(ConfigureVectorIndexesAction::class)->execute();
}

function cviaIndex(string $index): object
{
    return DB::selectOne(
        'SELECT indexdef, (quote_ident(indexname))::regclass::oid AS oid FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
        [$index],
    );
}

it('builds binary indexes over the quantized vectors', function (): void {
    cviaRun('binary');

    expect(cviaIndex('chunks_embedding_hnsw')->indexdef)
        ->toContain('USING hnsw')
        ->toContain('binary_quantize(embedding)')
        ->toContain('bit_hamming_ops')
        ->and(cviaIndex('chunks_questions_embedding_hnsw')->indexdef)->toContain('binary_quantize(questions_embedding)');
});

it('builds full indexes over the stored vectors', function (): void {
    cviaRun('full');

    expect(cviaIndex('chunks_embedding_hnsw')->indexdef)
        ->toContain('USING hnsw (embedding '.app(EmbeddingStorage::class)->type()->operatorClass().')')
        ->not->toContain('binary_quantize');
});

it('rebuilds only the indexes in the other kind', function (): void {
    cviaRun('full');
    $before = cviaIndex('chunks_embedding_hnsw')->oid;

    expect(cviaRun('full'))->toBeEmpty()
        ->and(cviaIndex('chunks_embedding_hnsw')->oid)->toBe($before)
        ->and(cviaRun('binary')->all())->toBe(['chunks_embedding_hnsw', 'chunks_questions_embedding_hnsw'])
        ->and(cviaIndex('chunks_embedding_hnsw')->oid)->not->toBe($before);
});

it('builds a missing index', function (): void {
    cviaRun('binary');
    DB::statement('DROP INDEX chunks_questions_embedding_hnsw');

    expect(cviaRun('binary')->all())->toBe(['chunks_questions_embedding_hnsw']);
});
