<?php

namespace Domain\Analytics\Services\Chat;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatchResult;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Throwable;

/**
 * Calls the internal analytics / charts MCP servers for the owner chat. Unlike
 * the agent path ({@see McpToolDispatcher})
 * there is no agent and no `mcp_servers` row: we hand {@see McpClient} a
 * transient, in-memory server pointed at the internal route and identify the
 * caller with a signed `X-Chatvia-Workspace-Id`. McpClient HMAC-signs internal
 * URLs automatically, so the call satisfies VerifyInternalMcpRequest's
 * workspace mode.
 */
class AnalyticsToolDispatcher
{
    public function __construct(
        private readonly McpClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function dispatch(User $workspace, string $toolName, string $url, array $arguments): ToolDispatchResult
    {
        $server = new AgentMcpServer;
        $server->forceFill([
            'url' => $url,
            'auth_type' => 'none',
            'auth_value' => null,
            'headers' => null,
            'timeout_ms' => 30000,
        ]);

        try {
            $result = $this->client->callTool($server, $toolName, $arguments, [
                'X-Chatvia-Workspace-Id' => $workspace->external_id,
            ]);
        } catch (Throwable $e) {
            return ToolDispatchResult::error('Tool error: '.$e->getMessage());
        }

        return $this->extract($result);
    }

    /**
     * Split the MCP `tools/call` envelope into the text the LLM sees and any
     * renderable artifacts (an `echarts_option` chart spec) — mirrors
     * {@see McpToolDispatcher::extractResult()}.
     *
     * @param  array<string, mixed>  $result
     */
    private function extract(array $result): ToolDispatchResult
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

        $text = $texts === [] ? (string) json_encode($result) : implode("\n", $texts);

        if ($isError) {
            return ToolDispatchResult::error($text);
        }

        $artifacts = [];
        $structured = $result['structuredContent'] ?? null;
        if (is_array($structured) && isset($structured['echarts_option']) && is_array($structured['echarts_option'])) {
            $artifacts[] = ['kind' => 'echarts_option', 'data' => $structured['echarts_option']];
        }

        return ToolDispatchResult::withArtifacts($text, $artifacts);
    }
}
