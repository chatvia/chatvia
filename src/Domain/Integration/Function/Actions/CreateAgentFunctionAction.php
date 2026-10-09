<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;
use Spatie\LaravelData\Optional;

/**
 * Create a workspace webhook function — from the dashboard form or the public
 * API. Names are unique per workspace, credentials are stored as the typed,
 * encrypted `auth_config`, and unless the caller decides, the approval gate
 * follows the verb: GET runs freely, anything that can change data asks first.
 */
class CreateAgentFunctionAction
{
    use GuardsFunctionDefinition;

    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(User $user, FunctionDefinitionData $data): AgentFunction
    {
        // A webhook tool is a workspace resource — own it by the workspace, not
        // the acting teammate, whose deletion (functions.user_id cascades)
        // would otherwise take the tool down with them.
        $owner = $user->workspace();
        $name = (string) $data->internal_name;

        $this->ensureNameAvailable($owner->id, $name);

        $method = $data->webhook_method instanceof Optional ? FunctionWebhookMethod::Post : $data->webhook_method;
        $authType = $data->auth_type instanceof Optional ? FunctionAuthType::None : $data->auth_type;

        return $this->guardUniqueName(fn (): AgentFunction => $owner->functions()->create([
            'internal_name' => $name,
            'display_name' => $data->display_name instanceof Optional ? null : $data->display_name,
            'description' => (string) $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => (string) $data->webhook_url,
            'webhook_method' => $method,
            'webhook_headers' => $data->webhook_headers instanceof Optional ? null : $data->webhook_headers,
            'auth_type' => $authType,
            'auth_config' => $this->authConfig->build($authType, $data->authValue(), $data->authHeaderName()),
            'requires_approval' => $data->requires_approval instanceof Optional ? $method->changesData() : $data->requires_approval,
            'timeout_ms' => $data->timeout_ms instanceof Optional ? AgentFunction::DEFAULT_TIMEOUT_MS : $data->timeout_ms,
            'is_enabled' => $data->is_enabled instanceof Optional ? true : $data->is_enabled,
        ]));
    }
}
