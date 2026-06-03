<?php

namespace Domain\Prompt\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Prompt\Data\Resources\AgentPromptPickerData;
use Domain\Prompt\Data\Resources\AgentPromptPickerListData;
use Domain\Prompt\Models\AgentPrompt;
use Spatie\LaravelData\DataCollection;

/**
 * List the user's reusable prompts trimmed to the picker shape (id / name /
 * prompt body), newest first.
 */
class ListPromptsForPickerAction
{
    public function execute(User $user): AgentPromptPickerListData
    {
        $prompts = $user->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $prompt): AgentPromptPickerData => AgentPromptPickerData::fromModel($prompt));

        return new AgentPromptPickerListData(
            data: new DataCollection(AgentPromptPickerData::class, $prompts),
        );
    }
}
