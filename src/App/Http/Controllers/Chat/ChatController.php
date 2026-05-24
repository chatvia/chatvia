<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Http\Request;
use Inertia\Inertia;

final class ChatController
{
    public function __invoke(Request $request, string $conversationId)
    {
        $agent = current_agent();

        return Inertia::render('chat/Chat', [
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'appearance' => $agent?->settings('appearance'),
        ]);
    }
}
