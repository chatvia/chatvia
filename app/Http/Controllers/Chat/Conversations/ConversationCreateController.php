<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Rules\ModelRule;
use App\Support\Agent;
use App\Support\ChatMemory;
use App\Support\Models;
use App\Support\Rag;
use Illuminate\Http\Request;

final class ConversationCreateController
{
    public function __invoke(Request $request, Models $models, ChatMemory $conversations)
    {
        $request->validate([
            'message' => ['required', 'string'],
            'model' => ['required','string', new ModelRule()],
        ]);

        $agent = Agent::resolveAgentFromSubdomain($request->subdomain());

        $conversationId = $conversations->createConversation(title: 'New Chat');

        $conversations->addUserMessage(
            conversationId: $conversationId,
            message: $request->input('message'),
            llmMessage: app(Rag::class)->makeLlmMessageFromUserMessage($agent, $request->input('message')),
            model: $request->input('model'),
        );

        return [
            'id' => $conversationId,
        ];
    }
}
