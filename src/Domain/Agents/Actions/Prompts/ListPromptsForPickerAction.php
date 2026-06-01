<?php

namespace Domain\Agents\Actions\Prompts;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\Prompts\AgentPromptPickerData;
use Domain\Agents\Data\Resources\Prompts\AgentPromptPickerListData;
use Domain\Agents\Models\AgentPrompt;
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
