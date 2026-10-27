<?php

declare(strict_types=1);

namespace Domain\Analytics\Services;

use Illuminate\Support\Arr;

/**
 * The reply-time histogram each analytics rollup row keeps
 * (`response_ms_hist`), so the median and the 95th percentile of any range
 * come from the rollups instead of a sort over every raw event.
 *
 * There are 24 buckets: under 100 ms, then 22 buckets spaced evenly on a log
 * scale up to 60 seconds (each about a third wider than the one before), then
 * 60 seconds and over. A percentile is read inside its bucket on the same log
 * scale, so it is usually within about 15% of the exact value. The rollup
 * migrations size the column for {@see BUCKETS}.
 */
final class ResponseTimeHistogram
{
    public const int BUCKETS = 24;

    private const float FLOOR_MS = 100.0;

    private const float CEILING_MS = 60000.0;

    /** The log-scaled buckets between the floor and the ceiling. */
    private const int SCALED = self::BUCKETS - 2;

    /** The bucket (0 to 23) a reply time falls in. */
    public static function bucketOf(int $ms): int
    {
        return match (true) {
            $ms < self::FLOOR_MS => 0,
            $ms >= self::CEILING_MS => self::BUCKETS - 1,
            default => 1 + min(self::SCALED - 1, (int) floor(log($ms / self::FLOOR_MS) / log(self::CEILING_MS / self::FLOOR_MS) * self::SCALED)),
        };
    }

    /**
     * The counts of one reply: 1 in its bucket, 0 everywhere else. All zeros
     * for a message without a reply time.
     *
     * @return list<int>
     */
    public static function countsFor(?int $ms): array
    {
        $counts = array_fill(0, self::BUCKETS, 0);

        if ($ms !== null) {
            $counts[self::bucketOf($ms)] = 1;
        }

        return $counts;
    }

    /**
     * The counts as a Postgres array literal, for a `?::integer[]` binding.
     *
     * @param  list<int>  $counts
     */
    public static function literal(array $counts): string
    {
        return '{'.Arr::join($counts, ',').'}';
    }

    /**
     * The reply time below which the share $quantile (0 to 1) of the replies
     * fall, or null when there are none. $maxMs, the slowest reply, closes
     * the last bucket, which has no upper edge of its own, and caps the
     * result: a value read inside a bucket is never slower than the slowest
     * reply.
     *
     * @param  array<int, int>  $counts  The summed counts by bucket (0 to 23).
     */
    public static function percentile(array $counts, float $quantile, ?int $maxMs = null): ?float
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return null;
        }

        $rank = $quantile * $total;
        $seen = 0;

        for ($bucket = 0; $bucket < self::BUCKETS; $bucket++) {
            $count = $counts[$bucket] ?? 0;

            if ($count > 0 && $seen + $count >= $rank) {
                $value = self::within($bucket, ($rank - $seen) / $count, $maxMs);

                return $maxMs !== null ? min($value, (float) $maxMs) : $value;
            }

            $seen += $count;
        }

        return (float) ($maxMs ?? self::CEILING_MS);
    }

    /** The reply time a fraction (0 to 1) of the way through a bucket. */
    private static function within(int $bucket, float $fraction, ?int $maxMs): float
    {
        $low = self::lowerEdge($bucket);
        $high = $bucket === self::BUCKETS - 1
            ? max(self::CEILING_MS, (float) ($maxMs ?? self::CEILING_MS))
            : self::lowerEdge($bucket + 1);

        if ($bucket === 0) {
            return $low + ($high - $low) * $fraction;
        }

        return $low * ($high / $low) ** $fraction;
    }

    private static function lowerEdge(int $bucket): float
    {
        return match (true) {
            $bucket === 0 => 0.0,
            $bucket === self::BUCKETS - 1 => self::CEILING_MS,
            default => self::FLOOR_MS * (self::CEILING_MS / self::FLOOR_MS) ** (($bucket - 1) / self::SCALED),
        };
    }
}
