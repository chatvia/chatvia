<?php

namespace Domain\Conversation\Services\Tooling\Registry;

use stdClass;

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
    /** JSON-Schema keys whose value is an object map (string-keyed). */
    private const OBJECT_KEYS = ['properties', '$defs', 'definitions', 'patternProperties'];

    /**
     * @param  array<string, mixed>  $parameters  JSON schema
     * @param  array<int, float>|null  $embedding
     */
    public function __construct(
        public readonly string $kind,       // 'mcp' or 'function'
        public readonly string $sourceId,   // typed row ref: connector_tool:{id} | mcp_tool:{id} | function:{id}
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
                'parameters' => $this->parameters === []
                    ? ['type' => 'object', 'properties' => new stdClass]
                    : self::normalizeSchema($this->parameters),
            ],
        ];
    }

    /**
     * PHP collapses `{}` and `[]` to the same empty array, so an MCP tool's
     * `{properties: {}}` round-trips through json_decode/json_encode as
     * `{properties: []}` — which Mistral rejects ("[] is not of type
     * 'object'", error 3260). Walk the schema and reinstate `stdClass` at
     * known object positions so the wire shape stays valid.
     */
    private static function normalizeSchema(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $child) {
            if (is_string($key) && in_array($key, self::OBJECT_KEYS, true) && $child === []) {
                $out[$key] = new stdClass;

                continue;
            }
            $out[$key] = self::normalizeSchema($child);
        }

        return $out;
    }
}
