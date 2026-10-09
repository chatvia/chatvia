<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Data;

use Domain\Integration\Tool\Enums\ToolSearchPool;
use Domain\Integration\Tool\Search\ToolHybridSearch;

/**
 * One ranked result of {@see ToolHybridSearch}.
 *
 * `score` is the fused RRF score (higher is better; only meaningful for
 * ordering). `channels` names the retrieval channels that found the tool —
 * `vector`, `bm25` or `bm25+vector`.
 */
final readonly class ToolSearchHitData
{
    public function __construct(
        public ToolEntry $entry,
        public float $score,
        public string $channels,
        public ToolSearchPool $pool,
    ) {}

    /**
     * The compact shape the routing debug logs print.
     *
     * @return array{kind: string, name: string, score: float, src: string}
     */
    public function toLogContext(): array
    {
        return [
            'kind' => $this->pool->value,
            'name' => $this->entry->name,
            'score' => round($this->score, 5),
            'src' => $this->channels,
        ];
    }
}
