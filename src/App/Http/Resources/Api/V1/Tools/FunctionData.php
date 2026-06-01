<?php

namespace App\Http\Resources\Api\V1\Tools;

use Carbon\CarbonInterface;
use Domain\Agents\Models\AgentFunction;
use Spatie\LaravelData\Data;
use Support\Enums\Functions\FunctionAuthType;
use Support\Enums\Functions\FunctionWebhookMethod;

/**
 * An account-level function the LLM can call. Pass `id` as `function_id` to
 * attach the function to an agent. Auth secrets are never returned;
 * `has_auth_value` only signals whether one is set.
 */
class FunctionData extends Data
{
    public function __construct(
        /**
         * The function's ID.
         */
        public string $id,
        /**
         * The function name the model calls.
         */
        public string $name,
        /**
         * What the function does, shown to the model.
         */
        public string $description,
        /**
         * JSON-schema describing the function's parameters.
         *
         * @var array<string, mixed>
         */
        public array $parameters_schema,
        /**
         * Where and how the function's webhook is called.
         */
        public FunctionWebhookData $webhook,
        /**
         * How the webhook call is authenticated.
         */
        public FunctionAuthType $auth_type,
        /**
         * Whether an auth secret is stored (the secret itself is never returned).
         */
        public bool $has_auth_value,
        /**
         * Webhook timeout in milliseconds.
         */
        public ?int $timeout_ms,
        /**
         * Whether the function is currently enabled.
         */
        public bool $is_enabled,
        /**
         * When the function was last called.
         */
        public ?CarbonInterface $last_invoked_at,
        /**
         * How many times the function has been called.
         */
        public int $invocation_count,
        /**
         * When the function was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentFunction $function): self
    {
        return new self(
            id: $function->external_id,
            name: $function->name,
            description: $function->description,
            parameters_schema: $function->parameters_schema ?? [],
            webhook: new FunctionWebhookData(
                url: $function->webhook_url,
                method: FunctionWebhookMethod::from($function->webhook_method),
                has_headers: $function->webhook_headers !== null && $function->webhook_headers !== [],
            ),
            auth_type: FunctionAuthType::from($function->auth_type),
            has_auth_value: $function->auth_config !== null,
            timeout_ms: $function->timeout_ms,
            is_enabled: (bool) $function->is_enabled,
            last_invoked_at: $function->last_invoked_at,
            invocation_count: (int) $function->invocation_count,
            created_at: $function->created_at,
        );
    }
}
