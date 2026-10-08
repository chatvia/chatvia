<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\ApiFunctionPayloadData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;
use Spatie\LaravelData\Optional;

/**
 * Provision an account-level webhook function. Function names must be unique
 * per account; the auth credentials are flattened into the typed
 * `auth_config` column the model stores (encrypted at rest). The approval
 * gate follows the verb, as on the dashboard: GET runs freely, anything that
 * can change data asks first.
 */
class CreateApiFunctionAction
{
    use GuardsFunctionDefinition;

    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(User $user, ApiFunctionPayloadData $data): AgentFunction
    {
        // Always key to the workspace owner, even if a teammate is passed in.
        $owner = $user->workspace();
        $name = (string) $data->internal_name;

        $this->ensureNameAvailable($owner->id, $name);

        $authType = $data->auth_type instanceof Optional ? 'none' : (string) $data->auth_type;
        $authValue = $data->auth_value instanceof Optional ? null : (string) $data->auth_value;
        $authHeader = $data->auth_header_name instanceof Optional ? null : (string) $data->auth_header_name;
        $method = $data->webhook_method instanceof Optional ? 'POST' : (string) $data->webhook_method;

        return $this->guardUniqueName(fn (): AgentFunction => $owner->functions()->create([
            'internal_name' => $name,
            'display_name' => $data->display_name instanceof Optional ? null : $data->display_name,
            'description' => (string) $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => (string) $data->webhook_url,
            'webhook_method' => $method,
            'webhook_headers' => $data->webhook_headers instanceof Optional ? null : $data->webhook_headers,
            'auth_type' => $authType,
            'auth_config' => $this->authConfig->build($authType, $authValue, $authHeader),
            'requires_approval' => $this->approvalDefaultFor($method),
            'timeout_ms' => $data->timeout_ms instanceof Optional ? 10000 : (int) $data->timeout_ms,
            'is_enabled' => $data->is_enabled instanceof Optional ? true : (bool) $data->is_enabled,
        ]));
    }
}
