<?php

namespace Domain\Integration\Function\Data\Resources;

use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the Settings → Functions page. Credentials never leave the
 * server: the page only learns whether one is stored, plus the (non-secret)
 * custom header name so the edit form can prefill it.
 */
#[TypeScript]
class WorkspaceFunctionRowData extends Data
{
    public function __construct(
        public string $id,
        public string $internal_name,
        public ?string $display_name,
        public string $description,
        /** The JSON Schema, pretty-printed for the editor. */
        public string $parameters_schema,
        public string $webhook_url,
        public FunctionWebhookMethod $webhook_method,
        public FunctionAuthType $auth_type,
        public string $auth_header_name,
        public bool $has_auth_value,
        public bool $is_enabled,
        public bool $requires_approval,
        public ?string $created_at,
        public int $agents_count,
    ) {}

    public static function fromModel(AgentFunction $function): self
    {
        return new self(
            id: $function->external_id,
            internal_name: $function->internal_name,
            display_name: $function->display_name,
            description: $function->description,
            parameters_schema: (string) json_encode($function->parameters_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            webhook_url: $function->webhook_url,
            webhook_method: $function->webhook_method,
            auth_type: $function->auth_type,
            auth_header_name: $function->auth_type === FunctionAuthType::Header ? (string) ($function->auth_config['name'] ?? '') : '',
            has_auth_value: $function->auth_config !== null,
            is_enabled: $function->is_enabled,
            requires_approval: $function->requires_approval,
            created_at: $function->created_at?->toFormattedDateString(),
            agents_count: (int) $function->agents_count,
        );
    }
}
