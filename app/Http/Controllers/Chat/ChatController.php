<?php

namespace App\Http\Controllers\Chat;

use App\Support\ChatMemory;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class ChatController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        return Inertia::render('chat/Chat', [
            'chat_session' => $memory->getOrGenerateSession(),
            'conversation_id' => $conversationId,
        ]);
    }
}
