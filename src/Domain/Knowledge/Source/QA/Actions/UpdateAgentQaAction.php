<?php

namespace Domain\Knowledge\Source\QA\Actions;

use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;

class UpdateAgentQaAction
{
    /**
     * @param  array<int, array{question: string}>  $questions
     */
    public function execute(AgentQuestionAnswer $qa, string $title, array $questions, string $answer): AgentQuestionAnswer
    {
        $qa->update([
            'title' => $title,
            'questions' => collect($questions)->pluck('question')->filter()->implode("\n"),
            'answer' => $answer,
        ]);

        return $qa;
    }
}
