<?php

namespace App\Http\Controllers\Agents\ChatLog;

use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentChatLogReviseAnswerController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        $validated = $request->validate([
            'message_id' => ['required', 'string'],
            'user_message' => ['required', 'string', 'max:5000'],
            'expected_response' => ['required', 'string', 'max:10000'],
        ]);

        // Verify the message belongs to this agent
        $message = ChatMessage::query()
            ->whereHas('conversation.session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('id', $validated['message_id'])
            ->where('role', 'assistant')
            ->firstOrFail();

        // Create the Q&A entry
        $qa = AgentQuestionAnswer::create([
            'agent_id' => $agent->id,
            'title' => substr($validated['user_message'], 0, 100),
            'questions' => $validated['user_message'],
            'answer' => $validated['expected_response'],
        ]);

        // Link the message to the Q&A entry
        $message->update(['agent_question_answer_id' => $qa->id]);

        return response()->json([
            'success' => true,
            'message' => 'Q&A entry created successfully. Future similar questions will use this answer.',
            'qa_id' => $qa->id,
        ]);
    }
}
