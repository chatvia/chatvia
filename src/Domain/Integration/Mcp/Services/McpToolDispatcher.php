<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\ResolvedMcpTool;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Exceptions\McpException;
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
        if (! $call->name->hasServerAndTool()) {
            return $this->error("Tool \"{$call->name->value}\" is not a recognized MCP tool.");
        }

        $slug = (string) $call->name->serverSlug;

        // Attached to this agent (workspace-owned, enabled, on the pivot).
        // Shared per-request lookup: the display + approval steps already
        // loaded this row for the same call.
        $server = $this->servers->attachedToAgent($agent, $slug);
        if (! $server instanceof AgentMcpServer) {
            return $this->error("MCP server \"{$slug}\" is not attached to this agent or is disabled.");
        }

        // Reject a blocked tool the LLM might emit from prior turns — the
        // catalog excludes blocked rows so the model shouldn't see them, but
        // turn history can still surface them. Resolves shared-catalog or own
        // tools transparently.
        $catalogEntry = $this->permissions->resolve($server, $call->name->tool);
        if ($catalogEntry?->permission === McpToolPermission::Blocked) {
            return $this->error("Tool \"{$call->name->value}\" is blocked.");
        }

        $arguments = $this->decodeArgs($call->arguments);
        if ($arguments === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        return $this->invoke($agent, $call, $server, $catalogEntry, $arguments);
    }

    /**
     * Run the call and record it. Argument values are visitor data (emails,
     * phone numbers, …), so logs carry their keys only.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function invoke(Agent $agent, ToolCallData $call, AgentMcpServer $server, ?ResolvedMcpTool $catalogEntry, array $arguments): ToolDispatchResult
    {
        $toolName = $call->name->tool;
        $start = microtime(true);

        try {
            $result = $this->client->callTool(
                $server,
                $toolName,
                $arguments,
                ToolCallerIdentity::headers($agent, $call->conversationExternalId, $call->chatterExternalId),
            );

            $this->record($server, $agent, $toolName, $catalogEntry?->description, error: null);

            Log::info('MCP tool dispatched', [
                'server' => $server->slug,
                'tool' => $toolName,
                'arg_keys' => array_keys($arguments),
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            // A failure the tool reports itself reads like this dispatcher's
            // own refusals ("Tool error: …").
            return $result->isError ? $this->error($result->text) : $result->toDispatchResult();
        } catch (Throwable $e) {
            // The full message stays with the workspace (the server row's
            // last_error); logs get the exception type only, since transport
            // messages can echo URLs, tokens or visitor data.
            $this->record($server, $agent, $toolName, $catalogEntry?->description, error: $e->getMessage());

            Log::warning('MCP tool dispatch failed', [
                'server' => $server->slug,
                'tool' => $toolName,
                'arg_keys' => array_keys($arguments),
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
