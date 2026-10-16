<?php

namespace Domain\Integration\Function\Data\Resources;

use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the agent's Functions page — the non-secret function fields the
 * table shows plus the per-agent attachment flag.
 */
#[TypeScript]
class AgentFunctionRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $internal_name,
        public ?string $display_name,
        public ?string $description,
        public string $webhook_url,
        public FunctionWebhookMethod $webhook_method,
        public bool $is_enabled,
        public ?string $last_error,
        public bool $is_attached,
    ) {}

    public static function fromModel(AgentFunction $function, bool $isAttached): self
    {
        return new self(
            external_id: $function->external_id,
            internal_name: $function->internal_name,
            display_name: $function->display_name,
            description: $function->description,
            webhook_url: $function->webhook_url,
            webhook_method: $function->webhook_method,
            is_enabled: (bool) $function->is_enabled,
            last_error: $function->last_error,
            is_attached: $isAttached,
        );
    }
}
