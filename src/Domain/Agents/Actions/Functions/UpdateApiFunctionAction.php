<?php

namespace Domain\Agents\Actions\Functions;

use Domain\Agents\Data\Functions\ApiFunctionPayloadData;
use Domain\Agents\Models\AgentFunction;
use Spatie\LaravelData\Optional;

/**
 * Patch an account-level function. When `auth_type` is included the typed
 * `auth_config` is rebuilt from the flat `auth_value` (+ optional
 * `auth_header_name`) fields; otherwise the existing config is left alone.
 */
class UpdateApiFunctionAction
{
    public function execute(AgentFunction $function, ApiFunctionPayloadData $data): AgentFunction
    {
        $attributes = [];

        foreach (['name', 'description', 'parameters_schema', 'webhook_url', 'webhook_method', 'webhook_headers', 'timeout_ms', 'is_enabled'] as $field) {
            if (! $data->{$field} instanceof Optional) {
                $attributes[$field] = $data->{$field};
            }
        }

        if (! $data->auth_type instanceof Optional) {
            $attributes['auth_type'] = $data->auth_type;
            $attributes['auth_config'] = $this->buildAuthConfig((string) $data->auth_type, $data);
        }

        $function->update($attributes);

        return $function->refresh();
    }

    /**
     * @return array<string, string>|null
     */
    private function buildAuthConfig(string $authType, ApiFunctionPayloadData $data): ?array
    {
        $value = $data->auth_value instanceof Optional ? '' : (string) $data->auth_value;
        $name = $data->auth_header_name instanceof Optional ? '' : (string) $data->auth_header_name;

        return match ($authType) {
            'bearer' => ['token' => $value],
            'header' => ['name' => $name, 'value' => $value],
            'hmac' => ['secret' => $value],
            default => null,
        };
    }
}
