<?php

namespace App\Http\Controllers\Chat;

use App\Support\ChatMemory;
use Illuminate\Http\Request;

class ChatDeleteAllConversationsController
{
    public function __invoke(Request $request, ChatMemory $memory)
    {
        $memory->deleteAllConversations();
    }
}
