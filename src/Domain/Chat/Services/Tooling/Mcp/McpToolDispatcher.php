<?php

namespace Domain\Chat\Services\Tooling\Mcp;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\ToolDispatcherInterface;
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

    public function dispatch(Agent $agent, string $name, string $arguments): string
    {
        [$slug, $toolName] = $this->parsePrefixedName($name);

        if ($slug === null || $toolName === null) {
            return $this->error("Tool \"{$name}\" is not a recognized MCP tool.");
        }

        $server = AgentMcpServer::query()
            ->where('agent_id', $agent->id)
            ->where('slug', $slug)
            ->where('enabled', true)
            ->first();

        if (! $server) {
            return $this->error("MCP server \"{$slug}\" is not configured or disabled for this agent.");
        }

        $args = $this->decodeArgs($arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        $start = microtime(true);

        try {
            $result = $this->client->callTool($server, $toolName, $args);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            $payload = $this->flattenResult($result);

            $server->forceFill([
                'last_invoked_at' => now(),
                'last_error' => null,
                'invocation_count' => $server->invocation_count + 1,
            ])->save();

            Log::info('MCP tool dispatched', [
                'server' => $server->slug,
                'tool' => $toolName,
                'latency_ms' => $latencyMs,
            ]);

            return $payload;
        } catch (Throwable $e) {
            $server->forceFill([
                'last_invoked_at' => now(),
                'last_error' => $e->getMessage(),
                'invocation_count' => $server->invocation_count + 1,
            ])->save();

            Log::warning('MCP tool dispatch failed', [
                'server' => $server->slug,
                'tool' => $toolName,
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
     * MCP returns `{content: [{type:'text', text:'...'}, {type:'image', ...}], isError?: bool}`.
     * The LLM needs a single string; flatten by concatenating text parts
     * (which is what virtually every MCP server emits in practice). If the
     * server flagged isError, prefix with the error convention so the LLM
     * recognises it.
     *
     * @param  array<string, mixed>  $result
     */
    private function flattenResult(array $result): string
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

        $payload = $texts === [] ? json_encode($result) : implode("\n", $texts);

        return $isError ? $this->error($payload) : $payload;
    }

    private function error(string $message): string
    {
        return 'Tool error: '.$message;
    }
}
