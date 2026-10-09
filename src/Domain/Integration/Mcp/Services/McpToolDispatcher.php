<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatches LLM-emitted tool calls that target an MCP server.
 *
 * The tool name is the prefixed form `mcp__{server_slug}__{tool}` (see
 * ToolName). The slug finds the right server row, then `tools/call` runs via
 * McpClient. Failures are returned as user-facing error strings (never
 * thrown) so the LLM can recover gracefully.
 */
class McpToolDispatcher implements ToolDispatcherInterface
{
    public function __construct(
        private readonly McpClient $client,
        private readonly McpToolPermissionResolver $permissions,
        private readonly McpServerLookup $servers,
    ) {}

    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult
    {
        $name = $call->name->value;

        if (! $call->name->hasServerAndTool()) {
            return $this->error("Tool \"{$name}\" is not a recognized MCP tool.");
        }

        $slug = (string) $call->name->serverSlug;
        $toolName = $call->name->tool;

        // Attached to this agent (workspace-owned, enabled, on the pivot).
        // Shared per-request lookup: the display + approval steps already
        // loaded this row for the same call.
        $server = $this->servers->attachedToAgent($agent, $slug);

        if (! $server) {
            return $this->error("MCP server \"{$slug}\" is not attached to this agent or is disabled.");
        }

        // Reject a blocked tool the LLM might emit from prior turns — the
        // catalog excludes blocked rows so the model shouldn't see them, but
        // turn history can still surface them. Resolves preset (membership +
        // shared catalog) or custom (per-server) transparently.
        $catalogRow = $this->permissions->resolve($server, $toolName);
        if ($catalogRow !== null && $catalogRow->permission === McpToolPermission::Blocked) {
            return $this->error("Tool \"{$name}\" is blocked.");
        }

        $args = $this->decodeArgs($call->arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        $start = microtime(true);

        try {
            $result = $this->client->callTool($server, $toolName, $args, ToolCallerIdentity::headers($agent, $call->conversationExternalId, $call->chatterExternalId));
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $dispatchResult = $this->extractResult($result);

            $this->record($server, $agent, $toolName, $catalogRow?->description, error: null);

            // Argument values are visitor data (emails, phone numbers, …):
            // log their keys only.
            Log::info('MCP tool dispatched', [
                'server' => $server->slug,
                'tool' => $toolName,
                'arg_keys' => array_keys($args),
                'latency_ms' => $latencyMs,
            ]);

            return $dispatchResult;
        } catch (Throwable $e) {
            // The full message stays with the workspace (the server row's
            // last_error); logs get the exception type only, since transport
            // messages can echo URLs, tokens or visitor data.
            $this->record($server, $agent, $toolName, $catalogRow?->description, error: $e->getMessage());

            Log::warning('MCP tool dispatch failed', [
                'server' => $server->slug,
                'tool' => $toolName,
                'arg_keys' => array_keys($args),
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);

            return $this->error($this->messageForModel($e));
        }
    }

    /**
     * What the model may see about a failed call. Only an error the MCP
     * server itself reported passes through; transport failures become a
     * generic sentence so URLs, response bodies and stack details never
     * reach the model (and so never the visitor).
     */
    private function messageForModel(Throwable $e): string
    {
        if ($e instanceof McpException && $e->serverMessage !== null) {
            return 'The tool reported an error: '.mb_substr($e->serverMessage, 0, 500);
        }

        if ($e instanceof McpException && $e->getCode() >= 400 && $e->getCode() < 600) {
            return "The tool's server returned an error (HTTP {$e->getCode()}). Try again later.";
        }

        if ($e instanceof McpException) {
            return "The tool's server could not be reached or sent an invalid reply. Try again later.";
        }

        return 'The tool failed unexpectedly. Try again later.';
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

        // Inline form descriptor (RenderFormTool) → rendered as a FormKit form.
        if (isset($structured['form']) && is_array($structured['form'])) {
            $artifacts[] = [
                'kind' => 'form',
                'data' => $structured['form'],
            ];
        }

        return $artifacts;
    }

    private function error(string $message): ToolDispatchResult
    {
        return ToolDispatchResult::error('Tool error: '.$message);
    }

    /**
     * Runs inside the SSE stream — inline cost stays at one event dispatch;
     * the queued listeners do the counter/rollup writes.
     */
    private function record(AgentMcpServer $server, Agent $agent, string $toolName, ?string $toolDescription, ?string $error): void
    {
        McpToolInvoked::dispatch(
            $agent->id,
            (int) $agent->user_id,
            (int) $server->id,
            (string) $server->slug,
            $toolName,
            $toolDescription,
            (string) $server->name,
            $server->connector_slug,
            $error,
            now(),
        );
    }
}
