<?php

namespace App\Http\Controllers\Chat;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ChatMessageFeedbackController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $request->validate([
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'feedback' => ['required', 'bool'],
        ]);

        $memory->setConversationId($request->input('conversation_id'))->updateMessage($request->input('message_id'), [
            'feedback' => $request->input('feedback'),
        ]);
    }
}
