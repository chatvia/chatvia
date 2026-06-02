<?php

namespace Domain\Prompt\Actions;

use Domain\Prompt\Data\CreatePromptData;
use Domain\Prompt\Models\AgentPrompt;

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
