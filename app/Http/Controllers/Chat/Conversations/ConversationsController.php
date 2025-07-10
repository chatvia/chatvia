<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ConversationsController
{
    public function __invoke(Request $request, ChatMemory $conversations)
    {
        return $conversations->getConversationsForCurrentSession(
            onlyVisible: true,
            orderByDate: true,
        );
    }
}
