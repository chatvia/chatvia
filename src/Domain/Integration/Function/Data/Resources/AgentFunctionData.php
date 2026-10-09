<?php

namespace Domain\Integration\Function\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Dashboard view of an account-level webhook function. Secrets never leave
 * the server: custom webhook headers are listed by name only (their values
 * often hold API keys), and auth credentials are not included at all.
 */
#[TypeScript]
class AgentFunctionData extends Data
{
    public function __construct(
        public string $external_id,
        public string $internal_name,
        public ?string $display_name,
        public string $description,
        /** @var array<string, mixed> */
        public array $parameters_schema,
        public string $webhook_url,
        public string $webhook_method,
        /** @var array<int, string> */
        public array $webhook_header_names,
        public bool $has_webhook_headers,
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
        $headerNames = self::headerNames($function->webhook_headers);

        return new self(
            external_id: $function->external_id,
            internal_name: $function->internal_name,
            display_name: $function->display_name,
            description: $function->description,
            parameters_schema: self::schemaToArray($function->parameters_schema),
            webhook_url: $function->webhook_url,
            webhook_method: $function->webhook_method->value,
            webhook_header_names: $headerNames,
            has_webhook_headers: $headerNames !== [],
            auth_type: $function->auth_type->value,
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
     * Names of the custom webhook headers, without their values.
     *
     * @return array<int, string>
     */
    private static function headerNames(mixed $headers): array
    {
        $names = [];

        foreach ((array) ($headers ?? []) as $header) {
            if (is_array($header) && isset($header['key']) && is_string($header['key']) && $header['key'] !== '') {
                $names[] = $header['key'];
            }
        }

        return $names;
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
