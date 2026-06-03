<?php

namespace Domain\Integration\Function\Actions;

use Domain\Integration\Function\Data\CreateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;

class CreateAgentFunctionAction
{
    public function execute(CreateAgentFunctionData $data): AgentFunction
    {
        return $data->user->functions()->create([
            'internal_name' => $data->internal_name,
            'display_name' => $data->display_name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $data->auth_config,
            // Seed the approval default from the verb when not specified:
            // GET reads, everything else writes.
            'requires_approval' => $data->requires_approval ?? ($data->webhook_method !== 'GET'),
            'is_enabled' => true,
        ]);
    }
}
