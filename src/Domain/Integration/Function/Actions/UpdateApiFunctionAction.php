<?php

namespace Domain\Integration\Function\Actions;

use Domain\Integration\Function\Data\ApiFunctionPayloadData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;
use Spatie\LaravelData\Optional;

/**
 * Patch an account-level function. When `auth_type` is included the typed
 * `auth_config` is rebuilt from the flat `auth_value` (+ optional
 * `auth_header_name`) fields; otherwise the existing config is left alone.
 */
class UpdateApiFunctionAction
{
    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(AgentFunction $function, ApiFunctionPayloadData $data): AgentFunction
    {
        $attributes = [];

        foreach (['internal_name', 'display_name', 'description', 'parameters_schema', 'webhook_url', 'webhook_method', 'webhook_headers', 'timeout_ms', 'is_enabled'] as $field) {
            if (! $data->{$field} instanceof Optional) {
                $attributes[$field] = $data->{$field};
            }
        }

        if (! $data->auth_type instanceof Optional) {
            $authValue = $data->auth_value instanceof Optional ? null : (string) $data->auth_value;
            $authHeader = $data->auth_header_name instanceof Optional ? null : (string) $data->auth_header_name;

            $attributes['auth_type'] = $data->auth_type;
            $attributes['auth_config'] = $this->authConfig->build((string) $data->auth_type, $authValue, $authHeader);
        }

        $function->update($attributes);

        return $function->refresh();
    }
}
