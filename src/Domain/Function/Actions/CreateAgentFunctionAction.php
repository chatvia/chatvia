<?php

namespace Domain\Function\Actions;

use Domain\Function\Data\CreateAgentFunctionData;
use Domain\Function\Models\AgentFunction;

class CreateAgentFunctionAction
{
    public function execute(CreateAgentFunctionData $data): AgentFunction
    {
        return $data->user->functions()->create([
            'name' => $data->name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $data->auth_config,
            'is_enabled' => true,
        ]);
    }
}
