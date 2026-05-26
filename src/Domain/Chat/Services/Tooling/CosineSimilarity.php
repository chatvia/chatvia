<?php

namespace Domain\Chat\Services\Tooling;

/**
 * Pure cosine-similarity math between two float vectors.
 *
 * Returns -INF for any unscoreable input (empty vector or zero
 * magnitude) so callers can sort by score and degenerate cases sink
 * to the bottom naturally.
 */
final class CosineSimilarity
{
    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function score(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return -INF;
        }

        $magA = $this->magnitude($a);
        $magB = $this->magnitude($b);

        if ($magA === 0.0 || $magB === 0.0) {
            return -INF;
        }

        $dot = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
        }

        return $dot / ($magA * $magB);
    }

    /**
     * @param  array<int, float>  $vector
     */
    public function magnitude(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }

        return sqrt($sum);
    }
}
