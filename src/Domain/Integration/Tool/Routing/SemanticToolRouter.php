<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Routing;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolSearchHitData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\Search\ToolHybridSearch;
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
        private readonly ToolRelevanceGate $gate,
        private readonly ConnectorClassifier $connectorClassifier,
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

        $entries = array_map(static fn (ToolSearchHitData $hit): ToolEntry => $hit->entry, $ranked);

        // Recall is done; now the precision gate. RRF ranked these candidates but
        // can't say whether the strongest is actually relevant — the reranker can.
        // On an off-topic turn (a greeting, a thank-you) this returns [], so the
        // caller ships zero tools and the model stays conversational. The gate
        // holds write tools to a stricter bar than reads (per-scope thresholds).
        $gated = $this->gate->gate($userMessage, $entries);

        // Optional small-model connector router: drop write tools whose connector
        // the classifier didn't pick for this turn. Off by default; fails open.
        $gated = $this->routeByConnector($agent, $userMessage, $gated);

        Log::debug('SemanticToolRouter: routing complete', [
            'agent_id' => $agent->id,
            'candidates' => count($entries),
            'returned' => count($gated),
            'gated_out' => count($entries) - count($gated),
            'top_5' => array_slice(array_map(static fn (ToolSearchHitData $hit): array => $hit->toLogContext(), $ranked), 0, 5),
        ]);

        return $gated;
    }

    /**
     * Small-model connector router (experiment, off by default). When enabled and
     * the gated set still contains a write, ask {@see ConnectorClassifier} which
     * connector(s) the turn is about, then drop write tools from connectors it
     * didn't pick. Reads, functions, and tools with no parseable connector pass
     * through. Fails open: a null route (declined / errored) prunes nothing.
     *
     * @param  array<int, ToolEntry>  $gated
     * @return array<int, ToolEntry>
     */
    private function routeByConnector(Agent $agent, string $userMessage, array $gated): array
    {
        if ($gated === [] || ! config('chatvia.agent.tools.routing.connector_classifier', false)) {
            return $gated;
        }

        // Only worth a model call if there's a CONNECTOR write to suppress — a write
        // MCP tool (mcp__{slug}__…). Webhook functions have no connector and are
        // never pruned, and an agent with no connectors has none of these — so we
        // skip the classifier (and its DB/LLM work) entirely rather than route a
        // turn that has nothing a connector route could touch.
        $hasConnectorWrite = array_any(
            $gated,
            static fn (ToolEntry $entry): bool => $entry->mutates && self::connectorSlug($entry) !== null,
        );
        if (! $hasConnectorWrite) {
            return $gated;
        }

        $picked = $this->connectorClassifier->classify($agent, $userMessage);
        if ($picked === null) {
            return $gated;
        }

        $allowed = array_flip($picked);
        $dropped = [];

        $kept = array_values(array_filter($gated, static function (ToolEntry $entry) use ($allowed, &$dropped): bool {
            if (! $entry->mutates) {
                return true;
            }
            $slug = self::connectorSlug($entry);
            if ($slug !== null && ! isset($allowed[$slug])) {
                $dropped[] = $entry->name;

                return false;
            }

            return true;
        }));

        if ($dropped !== []) {
            Log::debug('SemanticToolRouter: connector router pruned writes', [
                'agent_id' => $agent->id,
                'picked' => $picked,
                'dropped' => $dropped,
            ]);
        }

        return $kept;
    }

    /**
     * The connector slug of an MCP tool, or null for a webhook function or a
     * name without a server slug.
     */
    private static function connectorSlug(ToolEntry $entry): ?string
    {
        return $entry->kind === ToolKind::Mcp ? $entry->toolName()->serverSlug : null;
    }
}
