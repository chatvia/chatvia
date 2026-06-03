<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Picks the tools that ship to the LLM on a given turn. Delegates the
 * actual ranking to `ToolHybridSearch`, which does a single SQL query
 * that fuses dense-vector + BM25 retrieval across `mcp_tools` AND
 * `functions` into one RRF scoreboard.
 *
 * Multi-intent prompts like "fetch commits then render a heatmap" used
 * to drop the secondary tool below the cosine-threshold floor because
 * the dominant clause's vocabulary saturated the dense vector. BM25
 * fixes that, and the unified scoreboard means a relevant function
 * (e.g. `get_order_status` for "what's the status of order 1234?")
 * doesn't get drowned by 30 unrelated MCP tools — it ranks on its own
 * merits in the same competition.
 */
class SemanticToolRouter
{
    /** Hard cap on the routed set — past ~30 tools the LLM's selection accuracy drops noticeably. */
    public const int MAX_TOOLS = 30;

    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
        private readonly ToolRegistry $registry,
        private readonly ToolHybridSearch $search,
    ) {}

    /**
     * @return array<int, ToolEntry>
     */
    public function route(string $userMessage, Agent $agent): array
    {
        $queryVector = $this->embeddings->embedQuery($userMessage);

        if (! is_array($queryVector) || $queryVector === []) {
            Log::debug('SemanticToolRouter: empty query embedding, falling back to full pool');

            return $this->registry->for($agent);
        }

        $ranked = $this->search->search(
            agent: $agent,
            userMessage: $userMessage,
            queryVector: $queryVector,
            limit: self::MAX_TOOLS,
        );

        $entries = array_map(static fn (array $row): ToolEntry => $row['entry'], $ranked);

        Log::debug('SemanticToolRouter: routing complete', [
            'agent_id' => $agent->id,
            'returned' => count($entries),
            'top_5' => array_slice(array_map(static fn (array $r): array => [
                'kind' => $r['kind'],
                'name' => $r['entry']->name,
                'score' => round($r['score'], 5),
                'src' => $r['src'],
            ], $ranked), 0, 5),
        ]);

        return $entries;
    }
}
