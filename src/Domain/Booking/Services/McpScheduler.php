<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Domain\Agent\Models\Agent;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Exceptions\BookingException;
use Domain\Integration\Connector\Services\WorkspaceConnection;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Illuminate\Support\Str;

/**
 * A {@see Scheduler} that works through the tools of a scheduling service's
 * hosted MCP server, on the workspace's connection to it. The connection's
 * token is scoped to that server, so the service's REST API is not used.
 * Holds the calls both schedulers make: find the connection, call a tool,
 * read its reply as data.
 */
abstract class McpScheduler implements Scheduler
{
    /** Meeting types are cached briefly: a calendar render and its booking read them seconds apart. */
    protected const int MEETING_TYPES_TTL_MINUTES = 10;

    public function __construct(
        protected readonly McpClient $client,
        private readonly WorkspaceConnection $connections,
    ) {}

    /** The catalog slug of the connector this scheduler works through. */
    abstract protected function connectorSlug(): string;

    protected function server(Agent $agent): AgentMcpServer
    {
        return $this->connections->serverFor($agent, $this->connectorSlug()) ?? throw BookingException::notConnected();
    }

    /**
     * A read whose failure only means "try again later".
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function read(AgentMcpServer $server, string $tool, array $arguments): array
    {
        $result = $this->call($server, $tool, $arguments);

        if ($result->isError) {
            throw BookingException::unavailable(new McpException("{$this->connectorSlug()} {$tool} failed: ".Str::limit($result->text, 300)));
        }

        return $this->body($result);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function call(AgentMcpServer $server, string $tool, array $arguments): McpToolResultData
    {
        try {
            return $this->client->callTool($server, $tool, $arguments);
        } catch (McpException $e) {
            throw BookingException::unavailable($e);
        }
    }

    /**
     * The reply as data: the structured content, or the JSON text the server
     * also sends.
     *
     * @return array<string, mixed>
     */
    protected function body(McpToolResultData $result): array
    {
        if ($result->structuredContent !== null) {
            return $result->structuredContent;
        }

        $decoded = json_decode($result->text, true);

        return is_array($decoded) ? $decoded : [];
    }
}
