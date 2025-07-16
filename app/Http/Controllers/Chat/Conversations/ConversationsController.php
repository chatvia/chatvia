<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;

class ConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        return $memory->getConversationsForCurrentSession(
            onlyVisible: true,
            orderByDate: true,
        );
    }
}
