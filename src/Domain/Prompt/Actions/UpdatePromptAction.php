<?php

namespace Domain\Prompt\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Prompt\Data\UpdatePromptData;
use Domain\Prompt\Models\AgentPrompt;

class UpdatePromptAction
{
    public function execute(User $user, string $externalId, UpdatePromptData $data): AgentPrompt
    {
        /** @var AgentPrompt $prompt */
        $prompt = $user->prompts()->where('external_id', $externalId)->firstOrFail();

        $prompt->update([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);

        return $prompt;
    }
}
