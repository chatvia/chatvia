<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\UpdateAgentLlmData;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;

/**
 * Set the agent's single LLM, its hyperparameters and its reasoning effort.
 * The model lives as a column on the agent itself (`llm_id`); the
 * hyperparameters live in the `llm_settings` settings group, the effort in
 * `reasoning`.
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
        });
    }
}
