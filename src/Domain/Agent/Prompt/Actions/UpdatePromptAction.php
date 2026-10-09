<?php

namespace Domain\Agent\Prompt\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * Edit a reusable prompt. Prompts belong to the workspace (see
 * {@see CreatePromptAction}), so the lookup is scoped to the acting user's
 * workspace — a teammate can edit a prompt another teammate or the owner wrote.
 */
class UpdatePromptAction
{
    public function execute(User $user, string $externalId, UpdatePromptData $data): AgentPrompt
    {
        /** @var AgentPrompt $prompt */
        $prompt = $user->workspace()->prompts()->where('external_id', $externalId)->firstOrFail();

        $prompt->update([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);

        return $prompt;
    }
}
