<?php

namespace App\Http\Controllers\Chat\Conversations;

use Domain\Chat\Data\ChatInputPayload;
use Domain\Agents\Rules\AgentLLmChatRule;
use App\LLM\Operations\QueryRewriter;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
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

        ['lang' => $lang, 'query' => $query] = app(QueryRewriter::class)
            ->rewrite($request->input('message'));

        $sessionId = $session->firstOrCreateChatSession($agent);

        $conversationId = $memory
            ->setAgent($agent)
            ->setExternalSessionId($sessionId)
            ->createConversation(title: 'New Chat');

        $payload = ChatInputPayload::from([
            'external_session_id' => $sessionId,
            'external_conversation_id' => $conversationId,
            'external_message_id' => Str::uuid()->toString(),
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
