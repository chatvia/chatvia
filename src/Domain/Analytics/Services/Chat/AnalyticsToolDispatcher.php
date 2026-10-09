<?php

namespace Domain\Analytics\Services\Chat;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Throwable;

/**
 * Calls the internal analytics / charts MCP servers for the owner chat. Unlike
 * the agent path ({@see McpToolDispatcher}) there is no agent and no
 * `mcp_servers` row: we hand {@see McpClient} a transient, in-memory server
 * pointed at the internal route and identify the caller with
 * `X-Chatvia-Workspace-Id`. McpClient HMAC-signs first-party servers,
 * including that header, so the call satisfies VerifyInternalMcpRequest's
 * workspace mode. The result envelope is read the same way the agent path
 * reads it ({@see McpToolResultData}).
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
    public function dispatch(User $workspace, string $toolName, string $url, array $arguments): ToolDispatchResult
    {
        try {
            $result = McpToolResultData::fromEnvelope($this->client->callTool($this->serverFor($url), $toolName, $arguments, [
                'X-Chatvia-Workspace-Id' => $workspace->external_id,
            ]));
        } catch (Throwable $e) {
            return ToolDispatchResult::error('Tool error: '.$e->getMessage());
        }

        return $result->isError
            ? ToolDispatchResult::error($result->text)
            : new ToolDispatchResult($result->text, $result->artifacts());
    }

    /**
     * The connector slug is the route's last path segment (…/mcp/charts →
     * charts). McpClient only signs a server whose slug and canonical internal
     * URL agree, so a URL that isn't one of ours is never signed.
     */
    private function serverFor(string $url): AgentMcpServer
    {
        return new AgentMcpServer()->forceFill([
            'connector_slug' => basename((string) parse_url($url, PHP_URL_PATH)),
            'url' => $url,
            'auth_type' => McpAuthType::None,
            'auth_value' => null,
            'headers' => null,
            'timeout_ms' => self::TIMEOUT_MS,
        ]);
    }
}
