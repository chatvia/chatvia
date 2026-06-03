<?php

namespace Domain\Knowledge\Source\QA\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;

class CreateAgentQaAction
{
    /**
     * @param  array<int, array{question: string}>  $questions
     */
    public function execute(Agent $agent, string $title, array $questions, string $answer): AgentQuestionAnswer
    {
        return AgentQuestionAnswer::create([
            'agent_id' => $agent->id,
            'title' => $title,
            'questions' => $this->flatten($questions),
            'answer' => $answer,
        ]);
    }

    /**
     * @param  array<int, array{question: string}>  $questions
     */
    private function flatten(array $questions): string
    {
        return collect($questions)
            ->pluck('question')
            ->filter()
            ->implode(', ');
    }
}
