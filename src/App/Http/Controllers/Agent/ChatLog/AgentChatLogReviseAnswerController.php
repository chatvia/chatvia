<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Models\Agent;
use Domain\Source\QA\Actions\CreateQaFromMessageReviseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogReviseAnswerController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        CreateQaFromMessageReviseAction $createQa,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $validated = $request->validate([
            'message_id' => ['required', 'string'],
            'user_message' => ['required', 'string', 'max:5000'],
            'expected_response' => ['required', 'string', 'max:10000'],
        ]);

        $qa = $createQa->execute(
            agent: $agent,
            messageId: $validated['message_id'],
            userMessage: $validated['user_message'],
            expectedResponse: $validated['expected_response'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Q&A entry created successfully. Future similar questions will use this answer.',
            'qa_id' => $qa->id,
        ]);
    }
}
