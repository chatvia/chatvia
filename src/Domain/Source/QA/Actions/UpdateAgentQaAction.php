<?php

namespace Domain\Source\QA\Actions;

use Domain\Source\QA\Models\AgentQuestionAnswer;

class UpdateAgentQaAction
{
    /**
     * @param  array<int, array{question: string}>  $questions
     */
    public function execute(AgentQuestionAnswer $qa, string $title, array $questions, string $answer): AgentQuestionAnswer
    {
        $qa->update([
            'title' => $title,
            'questions' => collect($questions)->pluck('question')->filter()->implode(', '),
            'answer' => $answer,
        ]);

        return $qa;
    }
}
