<?php

namespace Domain\Integration\Tool\Routing;

use Domain\Agent\Models\Agent;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Small-model connector router: picks which connector(s) a turn is about, so
 * write tools of other connectors can be pruned.
 *
 * Similarity scores alone cannot do this well. A cross-encoder scores
 * `create_issue` (Linear) and `create_ticket` (Zendesk) almost the same, and can
 * rank the obvious match last (Linear for "file an engineering issue"). A small
 * instruction model can *reason* the intent→connector mapping ("ticket → support
 * → Zendesk") that a similarity score can't. This service makes one cheap
 * structured call to pick the relevant connector(s) for a turn;
 * {@see SemanticToolRouter} then drops write tools whose connector wasn't picked.
 *
 * Robustness rules:
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
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    /**
     * The connector slugs the message should route to.
     *
     * @return list<string>|null Picked slugs (subset of reachable), or null when
     *                           the router declined / failed — meaning "don't prune".
     */
    public function classify(Agent $agent, string $userMessage): ?array
    {
        $connectors = $this->reachableConnectors($agent);
        if ($connectors === []) {
            return null;
        }

        $slugs = array_keys($connectors);

        try {
            $reply = $this->completion->send($this->routeRequest($slugs, $connectors, $userMessage));
        } catch (Throwable $e) {
            Log::warning('ConnectorClassifier failed; skipping connector routing this turn', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $picked = $this->picked($reply->data, $slugs);

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

    /**
     * The structured-output completion request that asks the small model to
     * pick connectors. The JSON-schema `enum` is the reachable slug set, so the
     * model can only return connectors the agent actually has.
     *
     * @param  list<string>  $slugs
     * @param  array<string, string>  $connectors  slug => "name: description"
     */
    private function routeRequest(array $slugs, array $connectors, string $userMessage): ChatRequestData
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['connectors'],
            'properties' => [
                'connectors' => [
                    'type' => 'array',
                    // enum = the reachable set: the model can only return
                    // connectors the agent actually has.
                    'items' => ['type' => 'string', 'enum' => $slugs],
                ],
            ],
        ];

        return new ChatRequestData(
            model: $this->models->model(LlmTask::ToolRouting),
            messages: [
                ChatMessageData::system($this->systemPrompt()),
                ChatMessageData::user($this->userPrompt($userMessage, $connectors)),
            ],
            tag: AiCallTag::ToolRouting,
            temperature: 0.0,
            responseSchema: new ResponseSchemaData('connector_route', $schema, strict: false),
        );
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

        return "CONNECTORS:\n".Arr::join($lines, "\n")."\n\nMESSAGE:\n{$userMessage}";
    }

    /**
     * The picked slugs from the decoded reply; empty when the reply held no
     * usable `connectors` list.
     *
     * @param  array<array-key, mixed>|null  $data
     * @param  list<string>  $reachable
     * @return list<string>
     */
    private function picked(?array $data, array $reachable): array
    {
        $picked = $data['connectors'] ?? null;
        if (! is_array($picked)) {
            return [];
        }

        // Intersect with the reachable set defensively, even though the enum should
        // already guarantee it.
        $allowed = array_flip($reachable);

        return array_values(array_unique(array_filter(
            array_map(static fn ($s): string => filled($s) ? $s : '', $picked),
            static fn (string $s): bool => $s !== '' && isset($allowed[$s]),
        )));
    }

    /**
     * The agent's reachable connectors as slug => "{name}: {description}". Same
     * reachability rule as the hybrid search: a server attached via
     * `agent_mcp_server`.
     *
     * @return array<string, string>
     */
    private function reachableConnectors(Agent $agent): array
    {
        $servers = $agent->mcpServers()
            ->where('mcp_servers.is_enabled', true)
            ->get(['mcp_servers.slug', 'mcp_servers.name', 'mcp_servers.description']);

        $connectors = [];
        foreach ($servers as $server) {
            $description = Str::trim((string) $server->description);
            $connectors[$server->slug] = $description !== '' ? "{$server->name}: {$description}" : $server->name;
        }

        return $connectors;
    }
}
