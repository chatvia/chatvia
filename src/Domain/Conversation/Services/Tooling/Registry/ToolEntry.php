<?php

namespace Domain\Conversation\Services\Tooling\Registry;

/**
 * A single tool an agent can call, in a shape that decouples the
 * storage layer (McpTool / AgentFunction Eloquent rows) from the
 * routing and LLM-injection layers.
 *
 * `name` is what the LLM sees: prefixed for MCP tools
 * (`mcp__{slug}__{tool}`), unprefixed for webhook functions.
 *
 * `embedding` may be null when the row hasn't been embedded yet — the
 * router treats those as "ineligible to score" and either falls back to
 * the full pool or sets them aside until the embedding job lands.
 */
final class ToolEntry
{
    /**
     * @param  array<string, mixed>  $parameters  JSON schema
     * @param  array<int, float>|null  $embedding
     */
    public function __construct(
        public readonly string $kind,       // 'mcp' or 'function'
        public readonly int $sourceId,      // mcp_tools.id or functions.id
        public readonly string $name,       // prefixed for MCP, raw for function
        public readonly string $description,
        public readonly array $parameters,
        public readonly ?array $embedding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOpenAiFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters === [] ? ['type' => 'object', 'properties' => (object) []] : $this->parameters,
            ],
        ];
    }
}
