<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\UpdateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;

/**
 * Update an account-level webhook function from the dashboard form. The
 * function is looked up in the acting user's workspace, so teammates edit
 * the same functions the owner sees.
 */
class UpdateAgentFunctionAction
{
    use GuardsFunctionDefinition;

    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(User $user, string $externalId, UpdateAgentFunctionData $data): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->workspace()->functions()->where('external_id', $externalId)->firstOrFail();

        $this->ensureNameAvailable($function->user_id, $data->internal_name, $function->id);

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

        // Only touch the approval flag when the caller sent one, so an update
        // that omits it never resets the user's choice — except that switching
        // to a write verb turns approval on.
        $requiresApproval = $data->requires_approval ?? $this->approvalAfterMethodChange($function, $data->webhook_method);
        if ($requiresApproval !== null) {
            $attributes['requires_approval'] = $requiresApproval;
        }

        $this->guardUniqueName(fn (): bool => $function->update($attributes));

        return $function;
    }
}
