<?php

namespace Domain\Integration\Function\Actions;

use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\CreateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Create an account-level webhook function from the dashboard form.
 */
class CreateAgentFunctionAction
{
    use GuardsFunctionDefinition;

    public function execute(CreateAgentFunctionData $data): AgentFunction
    {
        // A webhook tool is a workspace resource — own it by the workspace, not
        // the acting employee, whose deletion (functions.user_id cascades) would
        // otherwise take the tool down with them.
        $owner = $data->user->workspace();

        $this->ensureNameAvailable($owner->id, $data->internal_name);

        return $this->guardUniqueName(fn (): AgentFunction => $owner->functions()->create([
            'internal_name' => $data->internal_name,
            'display_name' => $data->display_name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $data->auth_config,
            // Seed the approval default from the verb when not specified.
            'requires_approval' => $data->requires_approval ?? $this->approvalDefaultFor($data->webhook_method),
            'is_enabled' => true,
        ]));
    }
}
