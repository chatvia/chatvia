<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Data\Chat\ChatInputPayload;
use App\Rules\AgentLLmChatRule;
use App\Support\Chat\ChatMemory;
use App\Support\Llms;
use App\Support\OpenAI;
use Illuminate\Http\Request;

final class ConversationCreateController
{
    public function __invoke(Request $request, Llms $models, ChatMemory $memory)
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
        ]);

        $conversationId = $memory
            ->setAgent($agent)
            ->createConversation(title: 'New Chat');

        $memory->setConversationId($conversationId);

        $memory->addMessageViaPayload($payload);

        return [
            'id' => $conversationId,
        ];
    }
}
