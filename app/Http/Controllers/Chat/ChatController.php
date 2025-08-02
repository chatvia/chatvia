<?php

namespace App\Http\Controllers\Chat;

use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class ChatController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        return Inertia::render('chat/Chat', [
            'conversation_id' => $conversationId,
            'appearance' => current_agent()?->settings('appearance'),
        ]);
    }
}
