<?php

namespace Domain\Knowledge\Source\Qa\Actions;

use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * Adds a curated Q&A entry to an agent. When a visitor's message matches one
 * of its questions, the chat answers with the stored answer instead of the LLM.
 */
class CreateAgentQaAction
{
    public function execute(Agent $agent, QaEntryData $data): AgentQuestionAnswer
    {
        $qa = AgentQuestionAnswer::query()->create([
            'agent_id' => $agent->id,
            'title' => $data->title,
            'questions' => $data->questionLines(),
            'answer' => $data->answer,
        ]);

        AgentAnswersChanged::dispatch($agent->id);

        return $qa;
    }
}
