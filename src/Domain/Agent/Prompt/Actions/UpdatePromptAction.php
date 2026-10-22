<?php

declare(strict_types=1);

namespace Domain\Agent\Prompt\Actions;

use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * Edit a reusable prompt. Prompts belong to the workspace (see
 * {@see CreatePromptAction}), so the lookup is scoped to the acting user's
 * workspace — a teammate can edit a prompt another teammate or the owner wrote.
 */
class UpdatePromptAction
{
    public function execute(AgentPrompt $prompt, UpdatePromptData $data): AgentPrompt
    {
        $prompt->update([
            'name' => $data->name,
            'prompt' => $data->prompt,
        ]);

        return $prompt;
    }
}
