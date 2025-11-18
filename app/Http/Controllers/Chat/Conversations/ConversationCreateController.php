<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Data\Chat\ChatInputPayload;
use App\Rules\AgentLLmChatRule;
use App\Services\Infrastructure\LLM\OpenAI;
use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;

final class ConversationCreateController
{
    public function __invoke(Request $request, ChatMemory $memory, ChatSession $session)
    {
        $request->validate([
            'model' => ['required', 'string', new AgentLLmChatRule],
            'message' => ['required', 'string'],
        ]);

        $agent = current_agent();

        abort_if(boolean: ! $agent, code: 404);

        ['lang' => $lang, 'query' => $query] = OpenAI::rewriteQuery($request->input('message'));

        $payload = ChatInputPayload::from([
            'model' => $request->input('model'),
            'message' => $request->input('message'),
            'message_for_llm' => $query,
            'language' => $lang,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $conversationId = $memory
            ->setAgent($agent)
            ->setSessionId($session->firstOrCreateChatSession())
            ->createConversation(title: 'New Chat');

        $memory->setConversationId($conversationId);

        $memory->addMessageViaPayload($payload);

        return [
            'id' => $conversationId,
        ];
    }
}
