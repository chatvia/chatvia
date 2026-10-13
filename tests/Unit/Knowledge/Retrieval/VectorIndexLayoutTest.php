<?php

use Domain\Knowledge\Retrieval\Services\VectorIndexLayout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The live layout is read from the catalog: the partitions from the index's
 * options, the build size from its comment. Searches read the partitions on
 * every query, so they are cached until a rebuild forgets them.
 */
it('reads the flat indexes the install builds', function (): void {
    $state = app(VectorIndexLayout::class)->read('chunks_embedding_vchordrq_optimized');

    expect($state?->lists)->toBe([])
        ->and($state?->builtForRows)->toBeNull();
});

it('reads the partitions and build size of a partitioned index', function (): void {
    DB::statement('CREATE TABLE vector_layout_probe (embedding vector(3))');
    DB::statement(<<<'SQL'
CREATE INDEX vector_layout_probe_idx ON vector_layout_probe USING vchordrq (embedding vector_l2_ops)
WITH (options = 'residual_quantization = true
[build.internal]
lists = [12]
', probes = '2')
SQL);
    DB::statement("COMMENT ON INDEX vector_layout_probe_idx IS 'rows=123456'");

    $state = app(VectorIndexLayout::class)->read('vector_layout_probe_idx');

    expect($state?->lists)->toBe([12])
        ->and($state?->builtForRows)->toBe(123456);
});

it('returns null for a missing index and does not cache that', function (): void {
    $layout = app(VectorIndexLayout::class);

    expect($layout->read('no_such_index'))->toBeNull()
        ->and($layout->lists('no_such_index'))->toBeNull()
        ->and(Cache::has('vector-index:lists:no_such_index'))->toBeFalse();
});

it('caches the partitions until forgotten', function (): void {
    $layout = app(VectorIndexLayout::class);

    expect($layout->lists('chunks_embedding_vchordrq_optimized'))->toBe([]);

    // A rebuild elsewhere changed the index; the cached value still answers.
    Cache::put('vector-index:lists:chunks_embedding_vchordrq_optimized', [2000], 60);
    expect($layout->lists('chunks_embedding_vchordrq_optimized'))->toBe([2000]);

    $layout->forget('chunks_embedding_vchordrq_optimized');
    expect($layout->lists('chunks_embedding_vchordrq_optimized'))->toBe([]);
});
