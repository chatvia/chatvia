<?php

namespace Domain\Knowledge\Source\Qa\Actions;

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

        return $qa;
    }
}
