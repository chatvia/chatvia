<?php

namespace App\Http\Controllers\Chat;

use App\Support\Chat\ChatSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatHomeController
{
    public function __invoke(Request $request, ChatSession $chatSession)
    {
        return Inertia::render('chat/Home', [
            'chat_session' => $chatSession->getOrGenerateSession(),
        ]);
    }
}
