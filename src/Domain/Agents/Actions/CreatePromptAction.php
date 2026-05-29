<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Data\CreatePromptData;
use Domain\Agents\Models\AgentPrompt;

class CreatePromptAction
{
    public function execute(CreatePromptData $data): AgentPrompt
    {
        return $data->user->prompts()->create([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);
    }
}
