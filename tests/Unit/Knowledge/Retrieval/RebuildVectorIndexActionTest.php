<?php

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Illuminate\Support\Facades\DB;

/**
 * REINDEX takes no bindings, so the action interpolates the index name and
 * must only ever accept the known vector indexes.
 */
it('rebuilds a known vector index concurrently', function (): void {
    DB::shouldReceive('statement')->once()->with('REINDEX INDEX CONCURRENTLY chunks_embedding_vchordrq_optimized');

    app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized');
});

it('refuses any other index name', function (): void {
    DB::shouldReceive('statement')->never();

    expect(fn () => app(RebuildVectorIndexAction::class)->execute('chunks_pkey; DROP TABLE chunks'))
        ->toThrow(InvalidArgumentException::class);
});
