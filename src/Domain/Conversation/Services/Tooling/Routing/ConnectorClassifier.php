<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Small-model connector router — the "middle model" alternative to the embedding
 * connector prior that was tried and removed.
 *
 * The cross-encoder couldn't separate `create_issue` (Linear) from `create_ticket`
 * (Zendesk): their scores bunched, and it mis-ranked the obvious match (Linear last
 * for "file an engineering issue"). A small instruction model can *reason* the
 * intent→connector mapping ("ticket → support → Zendesk") that a similarity score
 * can't. This service makes one cheap structured call to pick the relevant
 * connector(s) for a turn; {@see SemanticToolRouter} then drops write tools whose
 * connector wasn't picked.
 *
 * Robustness, learned the hard way from the connector floor:
 *   - The JSON-schema `enum` is the reachable slug set, so the model CANNOT return a
 *     connector that isn't connected (no hallucinated routes).
 *   - It only ever PRUNES writes from non-picked connectors — never reads, never
 *     functions. A wrong read is cheap; the cost of a misroute is bounded.
 *   - Fails OPEN: any error, empty pick, or unparseable response returns `null`
 *     (the caller then does no connector pruning). A flaky router never strands a
 *     turn — it just falls back to the per-scope gate alone.
 */
class ConnectorClassifier
{
    public function __construct(
        private readonly LlmProviderInterface $provider,
    ) {}

    /**
     * The connector slugs the message should route to.
     *
     * @return list<string>|null  Picked slugs (subset of reachable), or null when
     *                            the router declined / failed — meaning "don't prune".
     */
    public function classify(Agent $agent, string $userMessage): ?array
    {
        $connectors = $this->reachableConnectors($agent);
        if ($connectors === []) {
            return null;
        }

        $slugs = array_keys($connectors);

        try {
            $response = $this->provider->completion([
                'model' => config('chatvia.ai.models.connector_router', config('chatvia.ai.models.guardrail')),
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'connector_route',
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['connectors'],
                            'properties' => [
                                'connectors' => [
                                    'type' => 'array',
                                    // enum = the reachable set: the model can only
                                    // return connectors the agent actually has.
                                    'items' => ['type' => 'string', 'enum' => $slugs],
                                ],
                            ],
                        ],
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($userMessage, $connectors)],
                ],
            ]);

            $content = $response->json('choices.0.message.content');
        } catch (Throwable $e) {
            Log::warning('ConnectorClassifier failed; skipping connector routing this turn', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $picked = $this->parse(is_string($content) ? $content : null, $slugs);

        // An empty pick ("no connector needed") is treated as "don't prune" rather
        // than "drop every write" — failing open keeps a misfire from starving the
        // turn. Real suppression only happens on a confident, non-empty route.
        if ($picked === []) {
            return null;
        }

        Log::debug('ConnectorClassifier: routed', [
            'agent_id' => $agent->id,
            'picked' => $picked,
        ]);

        return $picked;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You route a user's message to the integration(s) whose tools can act on it.
        Pick every connector that clearly applies, by its purpose — e.g. a support
        ticket or customer reply → a support connector; filing an engineering issue
        or pull request → an issue tracker / code host; a payment or refund → a
        billing connector. Pick none if the message needs no integration. Choose
        only from the provided connectors. Return only the structured JSON.
        PROMPT;
    }

    /**
     * @param  array<string, string>  $connectors  slug => "name: description"
     */
    private function userPrompt(string $userMessage, array $connectors): string
    {
        $lines = [];
        foreach ($connectors as $slug => $text) {
            $lines[] = "- {$slug} — {$text}";
        }

        return "CONNECTORS:\n".implode("\n", $lines)."\n\nMESSAGE:\n".$userMessage;
    }

    /**
     * @param  list<string>  $reachable
     * @return list<string>
     */
    private function parse(?string $content, array $reachable): array
    {
        if (! is_string($content) || $content === '') {
            return [];
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);
        $picked = is_array($data) ? ($data['connectors'] ?? null) : null;
        if (! is_array($picked)) {
            return [];
        }

        // Intersect with the reachable set defensively, even though the enum should
        // already guarantee it.
        $allowed = array_flip($reachable);

        return array_values(array_unique(array_filter(
            array_map(static fn ($s): string => is_string($s) ? $s : '', $picked),
            static fn (string $s): bool => $s !== '' && isset($allowed[$s]),
        )));
    }

    /**
     * The agent's reachable connectors as slug => "{name}: {description}". Same
     * reachability rule as the hybrid search: a server attached via
     * `agent_mcp_server`, or a global always-on builtin.
     *
     * @return array<string, string>
     */
    private function reachableConnectors(Agent $agent): array
    {
        $rows = DB::table('mcp_servers as s')
            ->leftJoin('agent_mcp_server as ams', function ($join) use ($agent): void {
                $join->on('ams.mcp_server_id', '=', 's.id')
                    ->where('ams.agent_id', $agent->id);
            })
            ->where('s.is_enabled', true)
            ->where(function ($q): void {
                $q->whereNotNull('ams.agent_id')
                    ->orWhere(function ($q2): void {
                        $q2->whereNull('s.user_id')
                            ->where('s.category', McpServerCategory::Builtin->value);
                    });
            })
            ->select('s.slug', 's.name', 's.description')
            ->distinct()
            ->get();

        $connectors = [];
        foreach ($rows as $row) {
            $desc = trim((string) $row->description);
            $connectors[$row->slug] = $desc !== '' ? $row->name.': '.$desc : (string) $row->name;
        }

        return $connectors;
    }
}
