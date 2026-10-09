<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Usage;

use Domain\Analytics\Data\Account\LabelCountData;

/**
 * What the AI relied on over a window: its most-called custom functions and
 * MCP tools, and its most-retrieved knowledge sources.
 */
final readonly class AiUsageData
{
    /**
     * @param  list<LabelCountData>  $topFunctions  Function name and call count.
     * @param  list<McpToolUsageData>  $topMcpTools
     * @param  list<LabelCountData>  $topKnowledgeSources  Source name and retrieval count.
     */
    public function __construct(
        public array $topFunctions,
        public array $topMcpTools,
        public array $topKnowledgeSources,
    ) {}
}
