<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Actions;

use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * Replaces the title, questions and answer of a curated Q&A entry.
 */
class UpdateAgentQaAction
{
    public function execute(AgentQuestionAnswer $qa, QaEntryData $data): AgentQuestionAnswer
    {
        $qa->update([
            'title' => $data->title,
            'questions' => $data->questionLines(),
            'answer' => $data->answer,
        ]);

        AgentAnswersChanged::dispatch($qa->agent_id);

        return $qa;
    }
}
