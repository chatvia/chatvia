<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Data\Account\UpdateAgentData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Actions\ResolveAgentPromptIdAction;
use Spatie\LaravelData\Optional;

/**
 * Saves the dashboard's agent edit form: name, chat slug and persona. Only the
 * fields the request sent are written. The persona is either a library prompt
 * or custom instructions, never both: setting one clears the other. Returns a
 * fresh copy of the agent.
 */
class UpdateAgentAction
{
    public function __construct(
        private readonly ResolveAgentPromptIdAction $resolvePromptId,
    ) {}

    public function execute(UpdateAgentData $data): Agent
    {
        $attributes = [];

        if (! $data->name instanceof Optional) {
            $attributes['name'] = $data->name;
        }

        if (! $data->slug instanceof Optional && $data->slug !== null) {
            $attributes['slug'] = $data->slug;
        }

        // A saved library prompt and a custom system_instructions string are
        // mutually exclusive. The FormRequest enforces that only one of the
        // two fields can be present per request; here we apply whichever
        // landed and clear the other so the agent never carries both.
        if (! $data->promptExternalId instanceof Optional) {
            $attributes['agent_prompt_id'] = $this->resolvePromptId->execute(
                $data->actor->workspace(),
                $data->promptExternalId,
            );
            $attributes['system_instructions'] = null;
        } elseif (! $data->systemInstructions instanceof Optional) {
            $attributes['system_instructions'] = $data->systemInstructions;
            $attributes['agent_prompt_id'] = null;
        }

        $data->agent->update($attributes);

        return $data->agent->fresh();
    }
}
