<?php

namespace Domain\Integration\Function\Actions;

use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\ApiFunctionPayloadData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;
use Spatie\LaravelData\Optional;

/**
 * Patch an account-level function. When `auth_type` is included the typed
 * `auth_config` is rebuilt from the flat `auth_value` (+ optional
 * `auth_header_name`) fields; otherwise the existing config is left alone.
 * A rename is checked against the workspace's other functions, and switching
 * to a write verb turns the approval gate on.
 */
class UpdateApiFunctionAction
{
    use GuardsFunctionDefinition;

    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(AgentFunction $function, ApiFunctionPayloadData $data): AgentFunction
    {
        $attributes = [];

        foreach (['internal_name', 'display_name', 'description', 'parameters_schema', 'webhook_url', 'webhook_method', 'webhook_headers', 'timeout_ms', 'is_enabled'] as $field) {
            if (! $data->{$field} instanceof Optional) {
                $attributes[$field] = $data->{$field};
            }
        }

        if (isset($attributes['internal_name'])) {
            $this->ensureNameAvailable($function->user_id, (string) $attributes['internal_name'], $function->id);
        }

        if (isset($attributes['webhook_method']) && $this->approvalAfterMethodChange($function, (string) $attributes['webhook_method']) === true) {
            $attributes['requires_approval'] = true;
        }

        if (! $data->auth_type instanceof Optional) {
            $authValue = $data->auth_value instanceof Optional ? null : (string) $data->auth_value;
            $authHeader = $data->auth_header_name instanceof Optional ? null : (string) $data->auth_header_name;

            $attributes['auth_type'] = $data->auth_type;
            $attributes['auth_config'] = $this->authConfig->build((string) $data->auth_type, $authValue, $authHeader);
        }

        $this->guardUniqueName(fn (): bool => $function->update($attributes));

        return $function->refresh();
    }
}
