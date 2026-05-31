<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\UpdateApiAgentData;
use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Optional;

/**
 * Patch top-level agent fields via the public API. Handles the mutually
 * exclusive `prompt_id` ↔ `system_instructions` rule — whichever the request
 * carries wins and clears the other.
 */
class UpdateApiAgentAction
{
    public function __construct(
        private readonly ResolveAgentPromptIdAction $resolvePromptId,
    ) {}

    public function execute(User $owner, Agent $agent, UpdateApiAgentData $data): Agent
    {
        $attributes = [];

        if (! $data->name instanceof Optional) {
            $attributes['name'] = $data->name;
        }

        if (! $data->metadata instanceof Optional) {
            $attributes['metadata'] = $data->metadata;
        }

        if (! $data->is_public instanceof Optional) {
            $attributes['is_public'] = $data->is_public;
        }

        if (! $data->prompt_id instanceof Optional) {
            $attributes['agent_prompt_id'] = $this->resolvePromptId->execute($owner, $data->prompt_id);
            $attributes['system_instructions'] = null;
        } elseif (! $data->system_instructions instanceof Optional) {
            $attributes['system_instructions'] = $data->system_instructions;
            $attributes['agent_prompt_id'] = null;
        }

        $agent->update($attributes);

        return $agent->refresh();
    }
}
