<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Data\Chat\ChatInputPayload;
use App\Rules\AgentLLmChatRule;
use App\Support\Chat\ChatMemory;
use App\Support\Llms;
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

        $payload = ChatInputPayload::fromRequest($request);

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
