<?php

use Domain\Analytics\Services\ResponseTimeHistogram;
use Illuminate\Support\Facades\DB;

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
    'just under a minute' => [59_999, 22],
    'a minute' => [60_000, 23],
    'ten minutes' => [600_000, 23],
]);

it('keeps the buckets in order of time', function (): void {
    $buckets = collect(range(50, 70_000, 37))->map(ResponseTimeHistogram::bucketOf(...));

    expect($buckets->all())->toBe($buckets->sort()->values()->all())
        ->and($buckets->unique()->count())->toBe(ResponseTimeHistogram::BUCKETS);
});

it('counts one reply in its bucket, and none for a message without a time', function (): void {
    expect(array_sum(ResponseTimeHistogram::countsFor(1500)))->toBe(1)
        ->and(ResponseTimeHistogram::countsFor(1500)[ResponseTimeHistogram::bucketOf(1500)])->toBe(1)
        ->and(ResponseTimeHistogram::countsFor(null))->toBe(array_fill(0, ResponseTimeHistogram::BUCKETS, 0))
        ->and(ResponseTimeHistogram::literal([1, 0, 2]))->toBe('{1,0,2}');
});

it('reads a percentile within about 15% of the exact one', function (): void {
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
});

it('never reads a percentile slower than the slowest reply', function (): void {
    $counts = ResponseTimeHistogram::countsFor(3000);

    expect(ResponseTimeHistogram::percentile($counts, 0.95, 3000))->toBeLessThanOrEqual(3000.0)
        ->and(ResponseTimeHistogram::percentile(array_fill(0, ResponseTimeHistogram::BUCKETS, 0), 0.5))->toBeNull();
});

it('matches the size of the rollup columns', function (string $table): void {
    $default = DB::connection(config('chatvia.analytics.connection'))
        ->scalar('SELECT column_default FROM information_schema.columns WHERE table_name = ? AND column_name = ?', [$table, 'response_ms_hist']);

    expect($default)->toContain('ARRAY['.ResponseTimeHistogram::BUCKETS.']');
})->with(['analytics_agent_hourly_rollups', 'analytics_agent_daily']);
