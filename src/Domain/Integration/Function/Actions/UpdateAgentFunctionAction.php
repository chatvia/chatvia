<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Data\UpdateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;

class UpdateAgentFunctionAction
{
    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(User $user, string $externalId, UpdateAgentFunctionData $data): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->functions()->where('external_id', $externalId)->firstOrFail();

        $attributes = [
            'internal_name' => $data->internal_name,
            'display_name' => $data->display_name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $this->authConfig->merge(
                newType: $data->auth_type,
                existingType: $function->auth_type,
                existing: $function->auth_config,
                value: $data->auth_value,
                headerName: $data->auth_header_name ?: null,
            ),
        ];

        // Only touch the approval flag when the caller sent one, so an API
        // update that omits it never resets the user's choice.
        if ($data->requires_approval !== null) {
            $attributes['requires_approval'] = $data->requires_approval;
        }

        $function->update($attributes);

        return $function;
    }
}
