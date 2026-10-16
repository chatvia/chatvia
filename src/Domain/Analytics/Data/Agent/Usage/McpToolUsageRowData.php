<?php

namespace Domain\Analytics\Data\Agent\Usage;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One MCP tool on the dashboard's source-usage analytics page, with its call
 * count and last use.
 */
#[TypeScript]
class McpToolUsageRowData extends Data
{
    public function __construct(
        public string $tool_name,
        public ?string $tool_description,
        public ?string $server_name,
        public string $server_slug,
        public ?string $connector_slug,
        public int $count,
        public ?string $last_used_at,
    ) {}

    public static function fromUsage(McpToolUsageData $tool): self
    {
        return new self(
            tool_name: $tool->toolName,
            tool_description: $tool->toolDescription,
            server_name: $tool->serverName,
            server_slug: $tool->serverSlug,
            connector_slug: $tool->connectorSlug,
            count: $tool->calls,
            // The rollup timestamps come back from DB::table without a tz
            // suffix; stamp them as UTC so the browser parses them correctly.
            last_used_at: $tool->lastUsedOn === null ? null : Carbon::parse($tool->lastUsedOn, 'UTC')->toIso8601ZuluString(),
        );
    }
}
