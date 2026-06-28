<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Top-level entry point for picking which tools ship to the LLM on a
 * given turn. Sits between LlmSource and the rest of the tooling
 * layer:
 *
 *   - Ask SemanticToolRouter for the ranked tool set (hybrid vector +
 *     BM25 retrieval in SQL).
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
        private readonly SurfaceToolFilter $surfaceFilter,
        private readonly TokenUsageContext $usage,
    ) {}

    /**
     * @return array<int, array<string, mixed>> OpenAI tool definitions
     */
    public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        // Empty pool: skip the router (it embeds the message before anything else).
        if ($this->registry->for($agent) === []) {
            return [];
        }

        try {
            // Tool routing embeds the user message (SemanticToolRouter::route →
            // embedQuery) — and runs inside the streaming generator, after the
            // chat-turn build scope has already closed. Open its own scope so the
            // embed's tokens are attributed to the workspace.
            $routed = $this->usage->track(
                $agent->user_id,
                fn (): array => $this->router->route($userMessage, $agent),
                'tool-routing',
            );
        } catch (Throwable $e) {
            // Surface filter still applies on the fallback path — a surface-pinned
            // connector (e.g. charts on widget) must never leak in just because
            // routing fell back to the full pool.
            $pool = $this->surfaceFilter->apply($this->registry->for($agent), $conversationId);

            Log::warning('Tool router failed; falling back to full pool', [
                'agent_id' => $agent->id,
                'pool_size' => count($pool),
                'error' => $e->getMessage(),
            ]);

            return array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $pool);
        }

        if ($routed === []) {
            Log::debug('ToolSelector: routing returned no tools', [
                'agent_id' => $agent->id,
                'conversation_id' => $conversationId,
            ]);

            return [];
        }

        $stickyNames = $conversationId !== null ? $this->sticky->resolve($conversationId) : [];
        $stickyAdded = [];

        if ($stickyNames !== []) {
            $routedNames = [];
            foreach ($routed as $entry) {
                $routedNames[$entry->name] = true;
            }
            // Sticky union: pull anything the model used recently back
            // into the set even if the router didn't rank it high enough
            // this turn. Pulls from the full cached pool so it doesn't
            // matter whether the sticky tool is MCP or function.
            foreach ($this->registry->for($agent) as $entry) {
                if (isset($routedNames[$entry->name])) {
                    continue;
                }
                if (in_array($entry->name, $stickyNames, true)) {
                    $routed[] = $entry;
                    $stickyAdded[] = $entry->name;
                }
            }
        }

        // Drop tools whose connector isn't allowed on this conversation's surface
        // (e.g. Interactive Charts outside Hosted Chat). Runs last so a sticky or
        // routed match can't re-introduce a surface-pinned tool.
        $shipped = $this->surfaceFilter->apply($routed, $conversationId);

        Log::debug('ToolSelector: tools shipped to model', [
            'agent_id' => $agent->id,
            'conversation_id' => $conversationId,
            'shipped_count' => count($shipped),
            'sticky_added' => $stickyAdded,
            'shipped_names' => array_map(static fn (ToolEntry $entry): string => $entry->name, $shipped),
        ]);

        return array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $shipped);
    }
}
