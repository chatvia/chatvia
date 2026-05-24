<?php

namespace App\Http\Controllers\Chat;

use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatHomeController
{
    public function __invoke(Request $request, ChatSession $chatSession)
    {
        return Inertia::render('chat/Home', [
            'appearance' => current_agent()?->settings('appearance'),
        ]);
    }
}
