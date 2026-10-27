<?php

namespace Domain\Integration\Tool\Data;

use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\Routing\ToolRelevanceGate;
use Domain\Integration\Tool\ValueObjects\ToolName;
use stdClass;

/**
 * A single tool an agent can call, in a shape that decouples the storage layer
 * (connector_tools / mcp_tools / functions rows) from the routing and
 * LLM-injection layers.
 *
 * `name` is what the LLM sees ({@see ToolName}): prefixed for MCP tools
 * (`mcp__{slug}__{tool}`), unprefixed for webhook functions.
 *
 * `sourceId` is a typed row reference — `connector_tool:{id}`, `mcp_tool:{id}`
 * or `function:{id}` — so a log line can point at the exact row.
 *
 * `mutates` is the tool's side-effect class — true for write/delete tools
 * (connector/MCP `scope = write`, or a non-GET webhook function), false for
 * read-only ones. {@see ToolRelevanceGate} holds write tools to a stricter
 * relevance bar: a missed read is cheap (the user re-asks), a wrong mutation
 * executes. Defaults to true so an unclassified tool gets the safer, stricter
 * gate rather than slipping through as a read.
 *
 * Instances are cached by {@see ToolRegistry}; changing the properties means
 * changing its cache key too.
 */
final readonly class ToolEntryData
{
    /** JSON-Schema keys whose value is an object map (string-keyed). */
    private const array OBJECT_KEYS = ['properties', '$defs', 'definitions', 'patternProperties'];

    /**
     * @param  array<string, mixed>  $parameters  JSON schema
     */
    public function __construct(
        public ToolKind $kind,
        public string $sourceId,
        public string $name,
        public string $description,
        public array $parameters,
        public bool $mutates = true,
    ) {}

    public function toolName(): ToolName
    {
        return ToolName::parse($this->name);
    }

    /**
     * True for an MCP tool served by any of the given servers.
     *
     * @param  array<int, string>  $serverSlugs
     */
    public function isFromAnyServer(array $serverSlugs): bool
    {
        if ($this->kind !== ToolKind::Mcp) {
            return false;
        }

        $name = $this->toolName();

        return array_any($serverSlugs, static fn (string $slug): bool => $name->isFromServer($slug));
    }

    /**
     * A JSON-Schema column as an array: raw JSON text is decoded, anything
     * that is not an object schema becomes an empty one.
     *
     * @return array<string, mixed>
     */
    public static function decodeSchema(mixed $schema): array
    {
        $decoded = is_scalar($schema) ? json_decode((string) $schema, true) : $schema;

        return is_array($decoded) ? $decoded : [];
    }

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
     * The flat shape a voice call's session takes (the Realtime API):
     * `{type, name, description, parameters}`, with the same normalized
     * schema as {@see toOpenAiFormat()}.
     *
     * @return array<string, mixed>
     */
    public function toRealtimeFormat(): array
    {
        return ['type' => 'function', ...$this->toOpenAiFormat()['function']];
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
            if (collect(self::OBJECT_KEYS)->containsStrict($key) && $child === []) {
                $out[$key] = new stdClass;

                continue;
            }
            $out[$key] = self::normalizeSchema($child);
        }

        return $out;
    }
}
