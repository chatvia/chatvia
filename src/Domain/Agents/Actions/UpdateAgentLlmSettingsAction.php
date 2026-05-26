<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class UpdateAgentLlmSettingsAction
{
    public function execute(
        Agent $agent,
        float $temperature,
        int $topK,
        float $topP,
        float $presencePenalty,
        float $frequencyPenalty,
    ): void {
        $agent->llms()->updateExistingPivot($agent->llms()->pluck('llms.id'), [
            'temperature' => $temperature,
            'top_k' => $topK,
            'top_p' => $topP,
            'presence_penalty' => $presencePenalty,
            'frequency_penalty' => $frequencyPenalty,
        ]);
    }
}
