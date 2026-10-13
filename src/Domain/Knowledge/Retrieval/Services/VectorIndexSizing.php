<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

/**
 * The shape of a vchordrq index for a number of rows, following the
 * VectorChord guidance: below 100k rows a flat index (no partitions, every
 * vector scanned with quantized distances) is fast and needs no training;
 * above that, one level of about 4·√N partitions ("lists"), of which a search
 * probes a small share.
 *
 * K-means memory grows with lists × sampling_factor, so the sampling factor
 * shrinks as lists grow to keep the build inside a memory budget, and very
 * large layouts switch to hierarchical k-means to keep the build time sane.
 */
final class VectorIndexSizing
{
    /** Below this many rows the index stays flat (`lists = []`). */
    public const int FLAT_BELOW_ROWS = 100_000;

    public const int MIN_LISTS = 1024;

    public const int MAX_LISTS = 32768;

    /** VectorChord's default and maximum useful sample per partition. */
    private const int MAX_SAMPLING_FACTOR = 256;

    /** Fewer samples per partition makes the partitions noticeably worse. */
    private const int MIN_SAMPLING_FACTOR = 32;

    /** Above this many lists, Lloyd's k-means (time ∝ lists²) takes hours. */
    private const int HIERARCHICAL_ABOVE_LISTS = 8192;

    private const int KMEANS_ITERATIONS = 25;

    /**
     * The partitions to build for this many indexed rows.
     *
     * @return list<int> empty for a flat index
     */
    public function lists(int $rows): array
    {
        if ($rows < self::FLAT_BELOW_ROWS) {
            return [];
        }

        return [(int) min(self::MAX_LISTS, max(self::MIN_LISTS, round(4 * sqrt($rows))))];
    }

    /**
     * K-means samples `sampling_factor × lists` vectors and needs about
     * `4 · dimensions · lists · (sampling_factor + threads + 1)` bytes
     * (VectorChord's own estimate), so the factor is the largest that fits the
     * memory budget, between 32 and 256.
     *
     * @param  list<int>  $lists
     */
    public function samplingFactor(array $lists, int $dimensions, int $buildThreads, int $memoryBudgetMb): int
    {
        $partitions = max([1, ...$lists]);
        $fits = intdiv($memoryBudgetMb * 1024 * 1024, 4 * max(1, $dimensions) * $partitions) - $buildThreads - 1;

        return max(self::MIN_SAMPLING_FACTOR, min(self::MAX_SAMPLING_FACTOR, $fits));
    }

    /** The vchordrq `options` (TOML) of a flat index: nothing to train. */
    public function flatOptions(): string
    {
        return "residual_quantization = true\n[build.internal]\nlists = []\n";
    }

    /**
     * The vchordrq `options` (TOML) for a layout.
     *
     * @param  list<int>  $lists
     */
    public function options(array $lists, int $dimensions, int $buildThreads, int $memoryBudgetMb): string
    {
        if ($lists === []) {
            return $this->flatOptions();
        }

        $threads = max(1, min(255, $buildThreads));
        $samplingFactor = $this->samplingFactor($lists, $dimensions, $threads, $memoryBudgetMb);
        $hierarchical = max($lists) > self::HIERARCHICAL_ABOVE_LISTS
            ? "kmeans_algorithm.hierarchical = {}\n"
            : '';

        return "residual_quantization = true\n"
            ."[build.internal]\n"
            .'lists = ['.implode(', ', $lists)."]\n"
            ."build_threads = {$threads}\n"
            ."spherical_centroids = false\n"
            ."sampling_factor = {$samplingFactor}\n"
            .'kmeans_iterations = '.self::KMEANS_ITERATIONS."\n"
            .$hierarchical;
    }

    /**
     * The `probes` value for a layout: a share of each level's lists, at least
     * one. A flat index takes no probes at all (vchordrq rejects any).
     *
     * @param  list<int>  $lists
     */
    public function probes(array $lists, float $percent): string
    {
        return implode(',', array_map(
            static fn (int $partitions): int => max(1, min($partitions, (int) ceil($partitions * $percent / 100))),
            $lists,
        ));
    }
}
