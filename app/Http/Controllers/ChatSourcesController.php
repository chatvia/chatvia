<?php

namespace App\Http\Controllers;

use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;

class ChatSourcesController
{
    public function __invoke(Request $request, string $conversationId, ChatMemory $memory)
    {
        return $memory
            ->setAgent(current_agent())
            ->setConversationId($conversationId)
            ->getSources();
    }
}
