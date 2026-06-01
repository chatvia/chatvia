<?php

namespace Domain\Agents\Actions\Settings;

use Domain\Agents\Data\Settings\UpdateAgentLlmData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Illuminate\Support\Facades\DB;

/**
 * Set the agent's single LLM and its hyperparameters. The model lives as a
 * column on the agent itself (`llm_id`); the hyperparameters live in the
 * `llm_settings` settings group.
 */
class UpdateAgentLlmAction
{
    public function execute(Agent $agent, UpdateAgentLlmData $data): void
    {
        $llmId = Llm::query()->where('external_name', $data->model_external_name)->valueOrFail('id');

        DB::transaction(function () use ($agent, $data, $llmId): void {
            $agent->forceFill(['llm_id' => $llmId])->save();

            $agent->setSetting('temperature', $data->temperature, 'llm_settings');
            $agent->setSetting('top_k', $data->top_k, 'llm_settings');
            $agent->setSetting('top_p', $data->top_p, 'llm_settings');
            $agent->setSetting('presence_penalty', $data->presence_penalty, 'llm_settings');
            $agent->setSetting('frequency_penalty', $data->frequency_penalty, 'llm_settings');
        });
    }
}
