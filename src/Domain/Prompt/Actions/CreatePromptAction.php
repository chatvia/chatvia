<?php

namespace Domain\Prompt\Actions;

use Domain\Prompt\Data\CreatePromptData;
use Domain\Prompt\Models\AgentPrompt;

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
