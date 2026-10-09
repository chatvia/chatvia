<?php

namespace Domain\Agent\Prompt\Actions;

use Domain\Agent\Prompt\Data\CreatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * Adds a reusable prompt to the workspace library (Settings → Prompts).
 */
class CreatePromptAction
{
    public function execute(CreatePromptData $data): AgentPrompt
    {
        // A reusable prompt is a workspace resource — own it by the workspace,
        // not the acting employee, whose deletion (agent_prompts.user_id cascades)
        // would otherwise take the prompt down with them.
        return $data->user->workspace()->prompts()->create([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);
    }
}
