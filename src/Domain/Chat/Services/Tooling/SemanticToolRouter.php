<?php

namespace Domain\Chat\Services\Tooling;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Scores the active tool pool against the current user message and
 * returns an adaptive subset:
 *   - Every tool with cosine ≥ RELEVANCE_THRESHOLD passes.
 *   - At least MIN_TOOLS are returned (top-K by score) so cold-start
 *     turns aren't completely empty.
 *   - At most MAX_TOOLS — past ~30 tools the LLM's tool-selection
 *     accuracy drops noticeably.
 *
 * Tools without embeddings (`embedding === null`) are excluded from
 * scoring; they re-enter the pool only via the fallback path in
 * ToolSelector. This isolates the cold path from blocking chat.
 */
class SemanticToolRouter
{
    public const float RELEVANCE_THRESHOLD = 0.5;

    public const int MIN_TOOLS = 5;

    public const int MAX_TOOLS = 30;

    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
        private readonly CosineSimilarity $similarity,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $pool
     * @return array<int, ToolEntry>
     */
    public function route(string $userMessage, array $pool): array
    {
        if ($pool === []) {
            Log::debug('SemanticToolRouter: empty pool, nothing to route');

            return [];
        }

        $queryVector = $this->embeddings->embedQuery($userMessage);

        if (! is_array($queryVector) || $queryVector === []) {
            Log::debug('SemanticToolRouter: empty query embedding, returning full pool', [
                'pool_size' => count($pool),
            ]);

            return $pool;
        }

        if ($this->similarity->magnitude($queryVector) === 0.0) {
            Log::debug('SemanticToolRouter: zero-magnitude query vector, returning full pool', [
                'pool_size' => count($pool),
            ]);

            return $pool;
        }

        // Score every embeddable tool. Unembedded rows get a score of
        // -INF so they sort to the bottom and never get picked unless
        // the floor pulls them in (and the floor prefers embedded
        // ones; see the sort below).
        $scored = [];
        foreach ($pool as $entry) {
            $score = $entry->embedding === null
                ? -INF
                : $this->similarity->score($queryVector, $entry->embedding);
            $scored[] = ['entry' => $entry, 'score' => $score];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $passing = array_values(array_filter(
            $scored,
            static fn (array $row): bool => $row['score'] >= self::RELEVANCE_THRESHOLD,
        ));
        $passedThreshold = count($passing);

        $appliedFloor = false;
        // Floor: ensure at least MIN_TOOLS by topping up from the
        // highest-scoring tools that didn't pass the threshold.
        if ($passedThreshold < self::MIN_TOOLS) {
            $passing = array_slice($scored, 0, min(self::MIN_TOOLS, count($scored)));
            $appliedFloor = true;
        }

        $appliedCeiling = false;
        // Ceiling: cap at MAX_TOOLS, keeping the top-scoring ones.
        if (count($passing) > self::MAX_TOOLS) {
            $passing = array_slice($passing, 0, self::MAX_TOOLS);
            $appliedCeiling = true;
        }

        Log::debug('SemanticToolRouter: routing complete', [
            'user_message_len' => strlen($userMessage),
            'query_vector_dims' => count($queryVector),
            'pool_size' => count($pool),
            'passed_threshold' => $passedThreshold,
            'applied_floor' => $appliedFloor,
            'applied_ceiling' => $appliedCeiling,
            'selected_count' => count($passing),
            'top_scores' => array_slice(array_map(
                static fn (array $row): array => [
                    'name' => $row['entry']->name,
                    'score' => is_finite($row['score']) ? round($row['score'], 4) : 'unembedded',
                ],
                $scored,
            ), 0, 10),
            'selected_names' => array_map(
                static fn (array $row): string => $row['entry']->name,
                $passing,
            ),
        ]);

        return array_map(static fn (array $row): ToolEntry => $row['entry'], $passing);
    }
}
