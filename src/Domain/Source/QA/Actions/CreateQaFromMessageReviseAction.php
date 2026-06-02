<?php

namespace Domain\Source\QA\Actions;

use Domain\Agent\Models\Agent;
use Domain\Chat\Models\ChatMessage;
use Domain\Source\QA\Models\AgentQuestionAnswer;
use Illuminate\Support\Facades\DB;

class CreateQaFromMessageReviseAction
{
    public function execute(
        Agent $agent,
        string $messageId,
        string $userMessage,
        string $expectedResponse,
    ): AgentQuestionAnswer {
        return DB::transaction(function () use ($agent, $messageId, $userMessage, $expectedResponse): AgentQuestionAnswer {
            $message = ChatMessage::query()
                ->whereHas('conversation.session', fn ($q) => $q->where('agent_id', $agent->id))
                ->where('id', $messageId)
                ->where('role', 'assistant')
                ->firstOrFail();

            $qa = AgentQuestionAnswer::create([
                'agent_id' => $agent->id,
                'title' => substr($userMessage, 0, 100),
                'questions' => $userMessage,
                'answer' => $expectedResponse,
            ]);

            $message->update(['agent_question_answer_id' => $qa->id]);

            return $qa;
        });
    }
}
