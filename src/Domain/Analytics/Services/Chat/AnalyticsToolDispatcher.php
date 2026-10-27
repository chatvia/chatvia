<?php

namespace Domain\Analytics\Services\Chat;

use App\Identity\Workspace\Models\Workspace;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Data\ToolDispatchResultData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use Throwable;

/**
 * Calls the internal analytics / charts MCP servers for the owner chat. Unlike
 * the agent path ({@see McpToolDispatcher}) there is no agent and no
 * `mcp_servers` row: we hand {@see McpClient} a transient, in-memory server
 * pointed at the internal route and identify the caller with
 * `X-Chatvia-Workspace-Id`. McpClient HMAC-signs first-party servers,
 * including that header, so the call satisfies VerifyInternalMcpRequest's
 * workspace mode. McpClient reads the result envelope for both paths
 * ({@see McpToolResultData}).
 *
 * A call that throws gives the model a fixed sentence, never the exception
 * message: transport messages can carry internal URLs, response bodies or
 * stack details. The log gets the exception type and the tool name only, not
 * the workspace.
 */
class AnalyticsToolDispatcher
{
    private const int TIMEOUT_MS = 30000;

    public function __construct(
        private readonly McpClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function dispatch(Workspace $workspace, string $toolName, string $url, array $arguments): ToolDispatchResultData
    {
        try {
            $result = $this->client->callTool($this->serverFor($url), $toolName, $arguments, [
                'X-Chatvia-Workspace-Id' => $workspace->external_id,
            ]);
        } catch (Throwable $e) {
            Log::warning('Analytics tool call failed', [
                'tool' => $toolName,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);

            return ToolDispatchResultData::error('The tool failed unexpectedly. Try again later.');
        }

        return $result->toDispatchResult();
    }

    /**
     * The connector slug is the route's last path segment (…/mcp/charts →
     * charts). McpClient only signs a server whose slug and canonical internal
     * URL agree, so a URL that isn't one of ours is never signed.
     */
    private function serverFor(string $url): AgentMcpServer
    {
        return new AgentMcpServer()->forceFill([
            'connector_slug' => Uri::of($url)->pathSegments()->last() ?? '',
            'url' => $url,
            'auth_type' => McpAuthType::None,
            'auth_value' => null,
            'headers' => null,
            'timeout_ms' => self::TIMEOUT_MS,
        ]);
    }
}
