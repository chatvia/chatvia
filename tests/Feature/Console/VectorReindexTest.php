<?php

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Domain\Knowledge\Retrieval\Data\VectorIndexRebuildData;
use Domain\Knowledge\Retrieval\Enums\VectorIndexRebuild;

/**
 * vector:reindex runs weekly: it sizes both chunks vector indexes for the
 * current rows and only rebuilds the ones that need it (--force: all).
 */
it('keeps the flat indexes of a small table as they are', function (): void {
    $this->artisan('vector:reindex')
        ->expectsOutputToContain('Checking chunks_embedding_vchordrq_optimized')
        ->expectsOutputToContain('Checking chunks_questions_embedding_vchordrq')
        ->expectsOutputToContain('(flat)')
        ->assertSuccessful();
});

it('rebuilds every index with --force', function (): void {
    $this->mock(RebuildVectorIndexAction::class)
        ->shouldReceive('execute')
        ->twice()
        ->withArgs(fn (string $index, VectorIndexRebuild $when): bool => $when === VectorIndexRebuild::Always)
        ->andReturnUsing(fn (string $index): VectorIndexRebuildData => new VectorIndexRebuildData($index, 150_000, [1549], true));

    $this->artisan('vector:reindex', ['--force' => true])
        ->expectsOutputToContain('rebuilt for 150000 rows (lists [1549])')
        ->assertSuccessful();
});
