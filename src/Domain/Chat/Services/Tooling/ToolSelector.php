<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Top-level entry point for picking which tools ship to the LLM on a
 * given turn. Sits between LlmSource and the rest of the tooling
 * layer:
 *
 *   - Pull the active pool from ToolRegistry.
 *   - Ask SemanticToolRouter to narrow it.
 *   - Union sticky tools from recent assistant turns of the conversation.
 *   - Flatten the result to the OpenAI `tools` shape LlmSource expects.
 *
 * Fallback: any failure in routing (embedding API down, malformed
 * response, etc.) falls through to the full pool. Routing is a quality
 * optimization, not a correctness dependency — never let it block a turn.
 */
class ToolSelector
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly SemanticToolRouter $router,
        private readonly StickyToolResolver $sticky,
    ) {}

    /**
     * @return array<int, array<string, mixed>> OpenAI tool definitions
     */
    public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        $pool = $this->registry->for($agent);

        if ($pool === []) {
            Log::debug('ToolSelector: empty pool, no tools shipped to model', [
                'agent_id' => $agent->id,
                'conversation_id' => $conversationId,
            ]);

            return [];
        }

        try {
            $routed = $this->router->route($userMessage, $pool);
        } catch (Throwable $e) {
            Log::warning('Tool router failed; falling back to full pool', [
                'agent_id' => $agent->id,
                'pool_size' => count($pool),
                'error' => $e->getMessage(),
            ]);

            return array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $pool);
        }

        $stickyNames = $conversationId !== null ? $this->sticky->resolve($conversationId) : [];
        $stickyAdded = [];

        if ($stickyNames !== []) {
            $routedNames = [];
            foreach ($routed as $entry) {
                $routedNames[$entry->name] = true;
            }
            foreach ($pool as $entry) {
                if (isset($routedNames[$entry->name])) {
                    continue;
                }
                if (in_array($entry->name, $stickyNames, true)) {
                    $routed[] = $entry;
                    $stickyAdded[] = $entry->name;
                }
            }
        }

        Log::debug('ToolSelector: tools shipped to model', [
            'agent_id' => $agent->id,
            'conversation_id' => $conversationId,
            'pool_size' => count($pool),
            'shipped_count' => count($routed),
            'sticky_added' => $stickyAdded,
            'shipped_names' => array_map(static fn (ToolEntry $entry): string => $entry->name, $routed),
        ]);

        return array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $routed);
    }
}
