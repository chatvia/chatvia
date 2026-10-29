<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;

/**
 * Set the agent's single LLM, its hyperparameters, its reasoning effort and
 * the models chatters may pick from. The model lives as a column on the agent
 * itself (`llm_id`); the hyperparameters live in the `llm_settings` settings
 * group, the effort in `reasoning`, the choice in `model_choice`, the image
 * and video choices in `media_model_choice`. Tells the evals the agent's
 * answers may have changed.
 */
class UpdateAgentLlmAction
{
    public function execute(Agent $agent, UpdateAgentLlmData $data): void
    {
        $llmId = Llm::query()->whereKey($data->model_id)->valueOrFail('id');

        DB::transaction(function () use ($agent, $data, $llmId): void {
            $agent->forceFill(['llm_id' => $llmId])->save();

            $agent->setSetting('temperature', $data->temperature, 'llm_settings');
            $agent->setSetting('top_k', $data->top_k, 'llm_settings');
            $agent->setSetting('top_p', $data->top_p, 'llm_settings');
            $agent->setSetting('presence_penalty', $data->presence_penalty, 'llm_settings');
            $agent->setSetting('frequency_penalty', $data->frequency_penalty, 'llm_settings');
            $agent->setSetting('effort', $data->reasoning_effort->value, 'reasoning');
            $agent->setSetting('enabled', $data->model_choice_enabled, 'model_choice');
            $agent->setSetting('models', array_values(array_unique($data->offered_model_ids)), 'model_choice');
            $agent->setSetting(GeneratedMediaKind::Image->value, $data->image_model_choice_enabled, OfferedMediaModels::SETTINGS_GROUP);
            $agent->setSetting(GeneratedMediaKind::Video->value, $data->video_model_choice_enabled, OfferedMediaModels::SETTINGS_GROUP);
        });

        AgentAnswersChanged::dispatch($agent->id);
    }
}
