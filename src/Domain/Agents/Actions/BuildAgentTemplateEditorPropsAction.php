<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentLlmDefaultSettingsData;
use Domain\Agents\Data\Resources\AgentPromptPickerData;
use Domain\Agents\Data\Resources\AgentTemplateEditorPropsData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentPrompt;
use Spatie\LaravelData\DataCollection;

/**
 * Assemble the shared props the Settings → Templates editor renders next to
 * the template itself (LLM catalog, the user's prompts, language options,
 * appearance + widget tuning defaults).
 */
class BuildAgentTemplateEditorPropsAction
{
    public function execute(User $user): AgentTemplateEditorPropsData
    {
        $prompts = $user->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $prompt): AgentPromptPickerData => AgentPromptPickerData::fromModel($prompt));

        return new AgentTemplateEditorPropsData(
            catalog: $user->formattedLlms(),
            prompts: new DataCollection(AgentPromptPickerData::class, $prompts),
            availableLanguages: config('widget.available_languages', []),
            defaultLlmSettings: AgentLlmDefaultSettingsData::defaults(),
            appearanceDefaults: config('model-settings.defaults.'.Agent::class.'.appearance', []),
            widgetDefaults: config('model-settings.defaults.'.Agent::class.'.widget', []),
        );
    }
}
