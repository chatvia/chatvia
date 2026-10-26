<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\Routing\AlwaysAvailableToolResolver;
use Domain\Integration\Tool\Routing\SemanticToolRouter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Top-level entry point for picking which tools ship to the LLM on a
 * given turn. Sits between LlmSource and the rest of the tooling
 * layer:
 *
 *   - Ask SemanticToolRouter for the ranked tool set (hybrid vector +
 *     BM25 retrieval in SQL).
 *   - Union the agent's "always on" connector tools (AlwaysAvailableToolResolver).
 *   - Union sticky tools from recent assistant turns of the conversation.
 *   - Drop tools the conversation's surface can't render (SurfaceToolFilter),
 *     tools of a capability whose service is off (UnavailableToolFilter),
 *     and tools that need approval where no one can approve (ApprovalToolFilter;
 *     for an anonymous visitor, {@see selectForVisitorTurn()}).
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
        private readonly AlwaysAvailableToolResolver $alwaysAvailable,
        private readonly ApprovalToolFilter $approvalFilter,
        private readonly UnavailableToolFilter $unavailableFilter,
    ) {}

    /**
     * @return array<int, array<string, mixed>> OpenAI tool definitions
     */
    public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        // Empty pool: skip the router (it embeds the message before anything else).
        $pool = $this->registry->for($agent);
        if ($pool === []) {
            return [];
        }

        // Always-on connectors ship regardless of the router's verdict — a
        // dedicated tutor/CRM agent must see its own tools even on "hi". They
        // are excluded from the search itself (ToolHybridSearch), so when the
        // whole pool is pinned there is nothing left to search: skip the
        // router and its embedding call outright.
        $pinned = $this->alwaysAvailable->resolve($agent, $pool);
        $pinnedNames = array_fill_keys($this->names($pinned), true);
        $searchable = array_filter($pool, static fn (ToolEntry $entry): bool => ! isset($pinnedNames[$entry->name]));

        if ($searchable === []) {
            return $this->shipPinnedOnly($agent, $pinned, $conversationId);
        }

        try {
            $routed = $this->router->route($userMessage, $agent);
        } catch (Throwable $e) {
            return $this->shipFullPool($agent, $conversationId, $e);
        }

        // Union the always-on tools with whatever the router returned.
        $withPinned = $this->union($routed, $pinned);

        if ($withPinned === []) {
            Log::debug('ToolSelector: routing returned no tools', [
                'agent_id' => $agent->id,
                'conversation_id' => $conversationId,
            ]);

            return [];
        }

        $withSticky = $this->union($withPinned, $this->stickyTools($agent, $conversationId));

        // Drop tools whose connector isn't allowed on this conversation's surface
        // (e.g. Interactive Charts outside Hosted Chat). Runs last so a sticky or
        // routed match can't re-introduce a surface-pinned tool.
        $shipped = $this->forSurface($agent, $withSticky, $conversationId);

        Log::debug('ToolSelector: tools shipped to model', [
            'agent_id' => $agent->id,
            'conversation_id' => $conversationId,
            'shipped_count' => count($shipped),
            'always_on_added' => array_slice($this->names($withPinned), count($routed)),
            'sticky_added' => array_slice($this->names($withSticky), count($withPinned)),
            'shipped_names' => $this->names($shipped),
        ]);

        return $this->toOpenAiTools($shipped);
    }

    /**
     * The tools for a turn with no signed-in person (an anonymous visitor):
     * those of {@see selectForTurn()} without any that need approval, since
     * only a signed-in chatter or staff account may approve a call.
     *
     * @return array<int, array<string, mixed>> OpenAI tool definitions
     */
    public function selectForVisitorTurn(Agent $agent, string $userMessage, ?string $conversationId): array
    {
        $definitions = $this->selectForTurn($agent, $userMessage, $conversationId);

        return $definitions === [] ? [] : $this->approvalFilter->withoutGated($agent, $definitions);
    }

    /**
     * The whole pool is always-on: ship it (surface-filtered) without routing.
     *
     * @param  array<int, ToolEntry>  $pinned
     * @return array<int, array<string, mixed>>
     */
    private function shipPinnedOnly(Agent $agent, array $pinned, ?string $conversationId): array
    {
        $shipped = $this->forSurface($agent, $pinned, $conversationId);

        Log::debug('ToolSelector: whole pool is always-on; routing skipped', [
            'agent_id' => $agent->id,
            'conversation_id' => $conversationId,
            'shipped_names' => $this->names($shipped),
        ]);

        return $this->toOpenAiTools($shipped);
    }

    /**
     * Routing failed: ship the agent's full pool. The surface filter still
     * applies — a surface-pinned connector (e.g. charts on widget) must never
     * leak in just because routing fell back to the full pool.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shipFullPool(Agent $agent, ?string $conversationId, Throwable $e): array
    {
        $pool = $this->forSurface($agent, $this->registry->for($agent), $conversationId);

        Log::warning('Tool router failed; falling back to full pool', [
            'agent_id' => $agent->id,
            'pool_size' => count($pool),
            'error' => $e->getMessage(),
        ]);

        return $this->toOpenAiTools($pool);
    }

    /**
     * The entries this conversation's surface can use: none pinned to another
     * surface, none of a capability whose service is off, and none that need
     * an approval the surface cannot ask for.
     *
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, ToolEntry>
     */
    private function forSurface(Agent $agent, array $entries, ?string $conversationId): array
    {
        $usable = $this->unavailableFilter->apply($this->surfaceFilter->apply($entries, $conversationId));

        return $this->approvalFilter->apply($agent, $usable, $conversationId);
    }

    /**
     * The pool entries the model called in recent turns of this conversation,
     * so a follow-up ("tell me more about #3") keeps the tool it needs even when
     * the router didn't rank it this turn. Pulls from the full cached pool so
     * it doesn't matter whether the sticky tool is MCP or function.
     *
     * @return array<int, ToolEntry>
     */
    private function stickyTools(Agent $agent, ?string $conversationId): array
    {
        $stickyNames = $conversationId !== null ? $this->sticky->resolve($conversationId) : [];

        if ($stickyNames === []) {
            return [];
        }

        return array_values(array_filter(
            $this->registry->for($agent),
            static fn (ToolEntry $entry): bool => in_array($entry->name, $stickyNames, true),
        ));
    }

    /**
     * `$base` followed by every `$extra` entry whose name is not already in it.
     *
     * @param  array<int, ToolEntry>  $base
     * @param  array<int, ToolEntry>  $extra
     * @return array<int, ToolEntry>
     */
    private function union(array $base, array $extra): array
    {
        $seen = array_fill_keys($this->names($base), true);

        foreach ($extra as $entry) {
            if (! isset($seen[$entry->name])) {
                $base[] = $entry;
                $seen[$entry->name] = true;
            }
        }

        return $base;
    }

    /**
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, string>
     */
    private function names(array $entries): array
    {
        return array_map(static fn (ToolEntry $entry): string => $entry->name, $entries);
    }

    /**
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function toOpenAiTools(array $entries): array
    {
        return array_map(static fn (ToolEntry $entry): array => $entry->toOpenAiFormat(), $entries);
    }
}
