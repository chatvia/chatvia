<?php

namespace Domain\Agents\Actions\Prompts;

use App\Auth\Models\User;
use Domain\Agents\Data\Prompts\UpdatePromptData;
use Domain\Agents\Models\AgentPrompt;

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
