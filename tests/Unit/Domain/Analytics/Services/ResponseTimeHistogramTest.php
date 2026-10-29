<?php

use Domain\Analytics\Services\ResponseTimeHistogram;

/**
 * ResponseTimeHistogram: reply times land in 24 log-scale buckets, and a
 * percentile read back from the counts is close to the exact one, never
 * slower than the slowest reply.
 */
it('puts a reply time in its bucket', function (int $ms, int $bucket): void {
    expect(ResponseTimeHistogram::bucketOf($ms))->toBe($bucket);
})->with([
    'under 100 ms' => [42, 0],
    'at 100 ms' => [100, 1],
    'a minute or more' => [60_000, 23],
]);

it('reads a percentile within about 15% of the exact one, never slower than the slowest reply', function (): void {
    mt_srand(7);
    $times = collect(range(1, 5000))->map(fn (): int => (int) round(exp(mt_rand(5000, 10000) / 1000)));
    $counts = array_fill(0, ResponseTimeHistogram::BUCKETS, 0);
    foreach ($times as $ms) {
        $counts[ResponseTimeHistogram::bucketOf($ms)]++;
    }
    $sorted = $times->sort()->values();

    foreach ([0.5, 0.95] as $quantile) {
        $exact = $sorted[(int) floor($quantile * ($sorted->count() - 1))];

        expect(ResponseTimeHistogram::percentile($counts, $quantile, $sorted->last()))->toEqualWithDelta($exact, $exact * 0.15);
    }

    expect(ResponseTimeHistogram::percentile(ResponseTimeHistogram::countsFor(3000), 0.95, 3000))->toBeLessThanOrEqual(3000.0)
        ->and(ResponseTimeHistogram::percentile(array_fill(0, ResponseTimeHistogram::BUCKETS, 0), 0.5))->toBeNull();
});
