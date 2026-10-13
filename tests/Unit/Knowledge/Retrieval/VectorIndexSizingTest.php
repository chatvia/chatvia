<?php

use Domain\Knowledge\Retrieval\Services\VectorIndexSizing;

/**
 * The vchordrq layout follows VectorChord's guidance: flat below 100k rows,
 * then about 4·√N partitions (clamped), with k-means memory kept inside the
 * build budget and probes as a share of the partitions.
 */
it('stays flat below 100k rows and partitions about 4·√N above', function (int $rows, array $lists): void {
    expect((new VectorIndexSizing)->lists($rows))->toBe($lists);
})->with([
    'empty' => [0, []],
    'just below' => [99_999, []],
    '100k' => [100_000, [1265]],
    '1M' => [1_000_000, [4000]],
    '10M' => [10_000_000, [12649]],
    'beyond the ceiling' => [100_000_000, [32768]],
]);

it('shrinks the k-means sample as the partitions grow, within the memory budget', function (): void {
    $sizing = new VectorIndexSizing;

    // 4 bytes * 1024 dims * lists * (factor + threads + 1) must fit 2048 MB.
    expect($sizing->samplingFactor([1265], 1024, 4, 2048))->toBe(256)
        ->and($sizing->samplingFactor([4000], 1024, 4, 2048))->toBe(126)
        ->and($sizing->samplingFactor([12649], 1024, 4, 2048))->toBe(36)
        ->and($sizing->samplingFactor([32768], 1024, 4, 2048))->toBe(32)
        // A bigger embedding leaves room for fewer samples.
        ->and($sizing->samplingFactor([1265], 4096, 4, 2048))->toBe(98);
});

it('writes flat options without training settings, and partitioned ones with them', function (): void {
    $sizing = new VectorIndexSizing;

    expect($sizing->options([], 1024, 4, 2048))->toBe($sizing->flatOptions())
        ->and($sizing->flatOptions())->toContain('lists = []')
        ->and($sizing->options([4000], 1024, 4, 2048))->toBe(
            "residual_quantization = true\n[build.internal]\nlists = [4000]\nbuild_threads = 4\n"
            ."spherical_centroids = false\nsampling_factor = 126\nkmeans_iterations = 25\n"
        )
        // Lloyd's k-means takes hours at this size; hierarchical k-means does not.
        ->and($sizing->options([12649], 1024, 4, 2048))->toContain('kmeans_algorithm.hierarchical = {}');
});

it('probes a share of each level, at least one and at most all of it', function (): void {
    $sizing = new VectorIndexSizing;

    expect($sizing->probes([], 3))->toBe('')
        ->and($sizing->probes([2366], 3))->toBe('71')
        ->and($sizing->probes([1024], 0.01))->toBe('1')
        ->and($sizing->probes([100], 500))->toBe('100')
        ->and($sizing->probes([400, 160000], 10))->toBe('40,16000');
});
