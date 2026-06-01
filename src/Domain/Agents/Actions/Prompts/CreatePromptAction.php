<?php

namespace Domain\Agents\Actions\Prompts;

use Domain\Agents\Data\Prompts\CreatePromptData;
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
