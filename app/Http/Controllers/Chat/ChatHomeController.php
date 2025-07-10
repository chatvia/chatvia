<?php

namespace App\Http\Controllers\Chat;

use App\Support\ChatMemory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatHomeController
{
    public function __invoke(Request $request, ChatMemory $conversations)
    {
        return Inertia::render('chat/Home', [
            'chat_session' => $conversations->getOrGenerateSession(),
        ]);
    }
}
