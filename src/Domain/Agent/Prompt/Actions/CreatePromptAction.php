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
        // not the acting teammate.
        return $data->user->workspace()->prompts()->create([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);
    }
}
