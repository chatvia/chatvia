<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Data\Chat\ChatInputPayload;
use App\Rules\AgentLLmChatRule;
use App\Services\Infrastructure\LLM\OpenAI;
use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $sessionId = $session->firstOrCreateChatSession();

        $conversationId = $memory
            ->setAgent($agent)
            ->setSessionId($sessionId)
            ->createConversation(title: 'New Chat');

        $payload = ChatInputPayload::from([
            'session_id' => $sessionId,
            'conversation_id' => $conversationId,
            'message_id' => Str::ulid()->toString(),
            'model' => $request->input('model'),
            'message' => $request->input('message'),
            'sent_at' => now(),
            'message_for_llm' => $query,
            'language' => $lang,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer_url' => $request->header('referer') ?: $request->server('HTTP_REFERER'),
            'page_url' => $request->url(),
            'locale' => $request->header('accept-language') ? substr($request->header('accept-language'), 0, 5) : null,
        ]);

        $memory->setConversationId($conversationId);

        $memory->addMessageViaPayload($payload);

        return [
            'id' => $conversationId,
        ];
    }
}
