<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Analytics;

use Domain\Analytics\Data\Agent\Usage\McpToolUsageData;
use Spatie\LaravelData\Data;

/**
 * One MCP tool the agent called, with the server it belongs to — labels as
 * they were at call time — its call count and the last day it was called.
 */
class McpToolCallUsageData extends Data
{
    public function __construct(
        /**
         * The tool's name when it was called.
         */
        public string $tool_name,
        /**
         * The tool's description when it was called.
         */
        public ?string $tool_description,
        /**
         * The MCP server's display name.
         */
        public ?string $server_name,
        /**
         * The MCP server's slug.
         */
        public string $server_slug,
        /**
         * The catalog connector the server belongs to, when it is one.
         */
        public ?string $connector_slug,
        /**
         * How many times the agent called it.
         */
        public int $calls,
        /**
         * The last day it was called (`Y-m-d`).
         */
        public ?string $last_used_on,
    ) {}

    public static function fromUsage(McpToolUsageData $tool): self
    {
        return new self(
            tool_name: $tool->toolName,
            tool_description: $tool->toolDescription,
            server_name: $tool->serverName,
            server_slug: $tool->serverSlug,
            connector_slug: $tool->connectorSlug,
            calls: $tool->calls,
            last_used_on: $tool->lastUsedOn,
        );
    }
}
