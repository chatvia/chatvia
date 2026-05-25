<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatMessage;
use Domain\Sources\QA\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revises an assistant message by creating a Q&A entry and linking the message
 * to it. Lives in its own controller because it produces a Q&A resource as a
 * side effect — it isn't an action on the chat-log resource itself.
 */
class AgentChatLogReviseAnswerController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $validated = $request->validate([
            'message_id' => ['required', 'string'],
            'user_message' => ['required', 'string', 'max:5000'],
            'expected_response' => ['required', 'string', 'max:10000'],
        ]);

        $message = ChatMessage::query()
            ->whereHas('conversation.session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('id', $validated['message_id'])
            ->where('role', 'assistant')
            ->firstOrFail();

        $qa = AgentQuestionAnswer::create([
            'agent_id' => $agent->id,
            'title' => substr($validated['user_message'], 0, 100),
            'questions' => $validated['user_message'],
            'answer' => $validated['expected_response'],
        ]);

        $message->update(['agent_question_answer_id' => $qa->id]);

        return response()->json([
            'success' => true,
            'message' => 'Q&A entry created successfully. Future similar questions will use this answer.',
            'qa_id' => $qa->id,
        ]);
    }
}
