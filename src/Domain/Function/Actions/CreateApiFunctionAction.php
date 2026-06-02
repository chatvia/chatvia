<?php

namespace Domain\Function\Actions;

use App\Workspace\Models\User;
use Domain\Function\Data\ApiFunctionPayloadData;
use Domain\Function\Models\AgentFunction;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * Provision an account-level webhook function. Function names must be unique
 * per account; the auth credentials are flattened into the typed
 * `auth_config` column the model stores (encrypted at rest).
 */
class CreateApiFunctionAction
{
    public function execute(User $user, ApiFunctionPayloadData $data): AgentFunction
    {
        $name = (string) $data->name;

        if ($user->functions()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A function with this name already exists on your account.',
            ]);
        }

        $authType = $data->auth_type instanceof Optional ? 'none' : (string) $data->auth_type;

        /** @var AgentFunction $function */
        $function = $user->functions()->create([
            'name' => $name,
            'description' => (string) $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => (string) $data->webhook_url,
            'webhook_method' => $data->webhook_method instanceof Optional ? 'POST' : (string) $data->webhook_method,
            'webhook_headers' => $data->webhook_headers instanceof Optional ? null : $data->webhook_headers,
            'auth_type' => $authType,
            'auth_config' => $this->buildAuthConfig($authType, $data),
            'timeout_ms' => $data->timeout_ms instanceof Optional ? 10000 : (int) $data->timeout_ms,
            'is_enabled' => $data->is_enabled instanceof Optional ? true : (bool) $data->is_enabled,
        ]);

        return $function;
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
