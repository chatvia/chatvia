<?php

namespace Domain\Agents\Data\Resources\Functions;

use Carbon\CarbonInterface;
use Domain\Agents\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentFunctionData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public string $description,
        /** @var array<string, mixed> */
        public array $parameters_schema,
        public string $webhook_url,
        public string $webhook_method,
        /** @var array<int, array<string, string>> */
        public array $webhook_headers,
        public string $auth_type,
        public int $timeout_ms,
        public int $max_response_bytes,
        public bool $is_enabled,
        public ?CarbonInterface $last_invoked_at,
        public ?string $last_error,
        public int $invocation_count,
        public CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentFunction $function): self
    {
        return new self(
            external_id: $function->external_id,
            name: $function->name,
            description: $function->description,
            parameters_schema: self::schemaToArray($function->parameters_schema),
            webhook_url: $function->webhook_url,
            webhook_method: $function->webhook_method,
            webhook_headers: array_values((array) ($function->webhook_headers ?? [])),
            auth_type: $function->auth_type,
            timeout_ms: $function->timeout_ms,
            max_response_bytes: $function->max_response_bytes,
            is_enabled: $function->is_enabled,
            last_invoked_at: $function->last_invoked_at,
            last_error: $function->last_error,
            invocation_count: $function->invocation_count,
            created_at: $function->created_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function schemaToArray(mixed $schema): array
    {
        if ($schema === null) {
            return ['type' => 'object', 'properties' => (object) [], 'required' => []];
        }

        if (is_object($schema) && method_exists($schema, 'toArray')) {
            return $schema->toArray();
        }

        return (array) $schema;
    }
}
