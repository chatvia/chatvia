<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Data\Chat\ChatInputPayload;
use App\Rules\ModelRule;
use App\Support\ChatMemory;
use App\Support\Models;
use Illuminate\Http\Request;

final class ConversationCreateController
{
    public function __invoke(Request $request, Models $models, ChatMemory $memory)
    {
        $request->validate([
            'model' => ['required', 'string', new ModelRule],
            'message' => ['required', 'string'],
        ]);

        $agent = current_agent();

        abort_if(boolean: ! $agent, code: 404);

        $payload = ChatInputPayload::fromRequest($request);

        $conversationId = $memory->createConversation(title: 'New Chat');
        $memory->setConversationId($conversationId)->setAgent($agent);

        $memory->addMessageViaPayload($payload);

        return [
            'id' => $conversationId,
        ];
    }
}
