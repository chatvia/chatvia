<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Data\Account\UpdateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Prompt\Actions\ResolveAgentPromptIdAction;

class UpdateAgentAction
{
    public function __construct(
        private readonly ResolveAgentPromptIdAction $resolvePromptId,
    ) {}

    public function execute(UpdateAgentData $data): Agent
    {
        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->sub_domain !== null) {
            $attributes['sub_domain'] = $data->sub_domain;
        }

        // A saved library prompt and a custom system_instructions string are
        // mutually exclusive. The FormRequest enforces that only one of the
        // two fields can be present per request; here we apply whichever
        // landed and clear the other so the agent never carries both.
        if ($data->changePrompt) {
            $attributes['agent_prompt_id'] = $this->resolvePromptId->execute(
                $data->actor,
                $data->promptExternalId,
            );
            $attributes['system_instructions'] = null;
        } elseif ($data->changeSystemInstructions) {
            $attributes['system_instructions'] = $data->systemInstructions;
            $attributes['agent_prompt_id'] = null;
        }

        $data->agent->update($attributes);

        return $data->agent->fresh();
    }
}
