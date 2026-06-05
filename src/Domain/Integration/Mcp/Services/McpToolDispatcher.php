<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatchResult;
use Domain\Conversation\Services\Tooling\Registry\ToolCallerIdentity;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatches LLM-emitted tool calls that target an MCP server.
 *
 * The tool name is the prefixed form `mcp__{server_slug}__{tool}`. We split
 * the slug to find the right server row, then call `tools/call` via
 * McpClient. Failures are returned as user-facing error strings (never
 * thrown) so the LLM can recover gracefully.
 */
class McpToolDispatcher implements ToolDispatcherInterface
{
    public function __construct(
        private readonly McpClient $client,
    ) {}

    public function dispatch(Agent $agent, string $name, string $arguments, ?string $conversationExternalId = null): ToolDispatchResult
    {
        [$slug, $toolName] = $this->parsePrefixedName($name);

        if ($slug === null || $toolName === null) {
            return $this->error("Tool \"{$name}\" is not a recognized MCP tool.");
        }

        $server = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('slug', $slug)
            ->where('is_enabled', true)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_mcp_server')
                    ->whereColumn('agent_mcp_server.mcp_server_id', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agent->id);
            })
            ->first();

        if (! $server) {
            return $this->error("MCP server \"{$slug}\" is not attached to this agent or is disabled.");
        }

        // Reject a blocked tool the LLM might emit from prior turns — the
        // catalog excludes blocked rows so the model shouldn't see them, but
        // turn history can still surface them.
        $catalogRow = McpTool::query()
            ->where('mcp_server_id', $server->id)
            ->where('name', $toolName)
            ->first();
        if ($catalogRow !== null && $catalogRow->permission === McpToolPermission::Blocked) {
            return $this->error("Tool \"{$name}\" is blocked.");
        }

        $args = $this->decodeArgs($arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        $start = microtime(true);

        try {
            $result = $this->client->callTool($server, $toolName, $args, ToolCallerIdentity::headers($agent, $conversationExternalId));
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $dispatchResult = $this->extractResult($result);

            $this->record($server, $agent, $toolName, error: null);

            Log::info('MCP tool dispatched', [
                'server' => $server->slug,
                'tool' => $toolName,
                'args' => $this->summarizeArgs($args),
                'latency_ms' => $latencyMs,
            ]);

            return $dispatchResult;
        } catch (Throwable $e) {
            $this->record($server, $agent, $toolName, error: $e->getMessage());

            Log::warning('MCP tool dispatch failed', [
                'server' => $server->slug,
                'tool' => $toolName,
                'args' => $this->summarizeArgs($args),
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parsePrefixedName(string $name): array
    {
        if (! str_starts_with($name, 'mcp__')) {
            return [null, null];
        }

        $rest = substr($name, 5);
        $pos = strpos($rest, '__');
        if ($pos === false) {
            return [null, null];
        }

        $slug = substr($rest, 0, $pos);
        $tool = substr($rest, $pos + 2);

        return [$slug !== '' ? $slug : null, $tool !== '' ? $tool : null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeArgs(string $arguments): ?array
    {
        if ($arguments === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * MCP `tools/call` envelope is `{content: [...], isError?: bool,
     * structuredContent?: {...}}`. We split it into two:
     *
     *   - `text`      → concatenated `type: 'text'` parts of `content`. This
     *                   is what the LLM consumes on its next turn. Falls back
     *                   to a JSON dump if no text part is present.
     *
     *   - `artifacts` → derived from `structuredContent`. Servers that
     *                   render charts/maps/tables return a shape like
     *                   `{echarts_option: {...}}` here — we promote any
     *                   well-known key to a UI artifact the chat renderer
     *                   knows how to display. Unknown keys are ignored so
     *                   servers can ship structured side-data without us
     *                   crashing.
     *
     * @param  array<string, mixed>  $result
     */
    private function extractResult(array $result): ToolDispatchResult
    {
        $content = $result['content'] ?? [];
        $isError = (bool) ($result['isError'] ?? false);

        $texts = [];
        if (is_array($content)) {
            foreach ($content as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text' && isset($part['text'])) {
                    $texts[] = (string) $part['text'];
                }
            }
        }

        $text = $texts === [] ? json_encode($result) : implode("\n", $texts);
        if ($isError) {
            return $this->error($text);
        }

        $artifacts = $this->artifactsFromStructuredContent($result['structuredContent'] ?? null);

        return new ToolDispatchResult($text, $artifacts);
    }

    /**
     * Promote a server's structured-content payload to renderable UI
     * artifacts. Only well-known shapes are exposed today — anything else
     * is dropped so the chat UI never has to defend against arbitrary JSON.
     *
     * Known shapes:
     *   - `echarts_option` → ECharts JSON spec rendered as an interactive chart
     *
     * @return array<int, array<string, mixed>>
     */
    private function artifactsFromStructuredContent(mixed $structured): array
    {
        if (! is_array($structured)) {
            return [];
        }

        $artifacts = [];

        if (isset($structured['echarts_option']) && is_array($structured['echarts_option'])) {
            $artifacts[] = [
                'kind' => 'echarts_option',
                'data' => $structured['echarts_option'],
            ];
        }

        return $artifacts;
    }

    private function error(string $message): ToolDispatchResult
    {
        return ToolDispatchResult::text('Tool error: '.$message);
    }

    private function record(AgentMcpServer $server, Agent $agent, string $toolName, ?string $error): void
    {
        $now = now();

        $server->forceFill([
            'last_invoked_at' => $now,
            'last_error' => $error,
            'invocation_count' => $server->invocation_count + 1,
        ])->save();

        // Per-agent counter on the server pivot — kept as a summary signal
        // (how often did this agent touch this server in any way) alongside
        // the per-tool breakdown below.
        DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->where('mcp_server_id', $server->id)
            ->update([
                'invocation_count' => DB::raw('invocation_count + 1'),
                'last_invoked_at' => $now,
            ]);

        // Per-(agent, tool) counter — drives the tool-level breakdown in
        // the Sources analytics page. INSERT...SELECT silently no-ops when
        // the LLM called a tool that isn't in our catalog (e.g. catalog
        // hasn't synced since the server added a new tool), so a stale
        // catalog can never break the dispatch path.
        DB::statement(
            'INSERT INTO agent_mcp_tool (agent_id, mcp_tool_id, invocation_count, last_invoked_at)
             SELECT ?, mcp_tools.id, 1, ?
             FROM mcp_tools
             WHERE mcp_tools.mcp_server_id = ? AND mcp_tools.name = ?
             ON CONFLICT (agent_id, mcp_tool_id) DO UPDATE
               SET invocation_count = agent_mcp_tool.invocation_count + 1,
                   last_invoked_at = EXCLUDED.last_invoked_at',
            [$agent->id, $now, $server->id, $toolName]
        );
    }

    /**
     * JSON-encode tool args for logging; truncate so a giant inline
     * payload (HTML body, base64 image, etc.) doesn't blow up the log.
     *
     * @param  array<string, mixed>  $args
     */
    private function summarizeArgs(array $args): string
    {
        $encoded = json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '<unserializable>';
        }

        return mb_strlen($encoded) > 500
            ? mb_substr($encoded, 0, 500).'…[truncated]'
            : $encoded;
    }
}
