<?php

namespace App\Http\Controllers\Agent\ChatLog;

use App\Http\Requests\Agent\ChatLog\ReviseAnswerRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateQaFromMessageReviseAction;
use Illuminate\Http\JsonResponse;

/**
 * Turns an operator's corrected answer from the chat log into a Q&A entry.
 */
class AgentChatLogReviseAnswerController
{
    public function __invoke(
        ReviseAnswerRequest $request,
        Agent $agent,
        CreateQaFromMessageReviseAction $createQa,
    ): JsonResponse {
        $qa = $createQa->execute(
            agent: $agent,
            messageId: $request->messageId(),
            userMessage: $request->userMessage(),
            expectedResponse: $request->expectedResponse(),
        );

        return response()->json([
            'success' => true,
            'message' => __('Q&A entry created successfully. Future similar questions will use this answer.'),
            'qa_id' => $qa->id,
        ]);
    }
}
