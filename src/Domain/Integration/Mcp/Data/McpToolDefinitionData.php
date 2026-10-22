<?php

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Tool\Data\ToolName;

/**
 * One tool as an MCP server describes it — an entry of a `tools/list`
 * response, or the same shape read from one of Chatvia's own code-defined
 * servers. Built straight from the decoded payload, so the catalog sync never
 * handles raw arrays; entries without a usable name are dropped on the way in.
 */
final readonly class McpToolDefinitionData
{
    /**
     * @param  array<string, mixed>  $inputSchema  JSON Schema of the tool's arguments.
     * @param  array<string, mixed>|null  $annotations  MCP tool annotations (`readOnlyHint`, …).
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public ?array $annotations,
    ) {}

    /**
     * Parse one `tools/list` entry; null when it is not a tool definition.
     */
    public static function fromArray(mixed $tool): ?self
    {
        if (! is_array($tool) || blank($tool['name'] ?? null)) {
            return null;
        }

        return new self(
            name: $tool['name'],
            description: (string) ($tool['description'] ?? ''),
            inputSchema: is_array($tool['inputSchema'] ?? null)
                ? $tool['inputSchema']
                : ['type' => 'object', 'properties' => (object) []],
            annotations: is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null,
        );
    }

    /**
     * Parse a whole `tools/list` array, skipping malformed entries.
     *
     * @param  array<int|string, mixed>  $tools
     * @return list<self>
     */
    public static function listFrom(array $tools): array
    {
        return array_values(array_filter(array_map(self::fromArray(...), $tools)));
    }

    /**
     * Read-only when the server says so (`readOnlyHint: true`); everything
     * else may change data.
     */
    public function scope(): McpToolScope
    {
        return ($this->annotations['readOnlyHint'] ?? false) === true
            ? McpToolScope::Read
            : McpToolScope::Write;
    }

    /**
     * The permission a workspace starts with: read-only tools run freely,
     * write tools ask a person first (human-in-the-loop).
     */
    public function defaultPermission(): McpToolPermission
    {
        return $this->scope() === McpToolScope::Read
            ? McpToolPermission::Allow
            : McpToolPermission::Approval;
    }

    /**
     * Whether the compiled name (`mcp__{slug}__{name}`) is a valid LLM tool
     * name; tools that would break it are left out of the catalog.
     */
    public function fitsToolNameFor(string $serverSlug): bool
    {
        return ToolName::forMcp($serverSlug, $this->name)->isValidForLlm();
    }
}
