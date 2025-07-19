<?php

namespace App\Http\Controllers\Agents\Playground;

use App\Models\Agent;
use App\Support\Chat\ChatMemory;
use Illuminate\Http\Request;

class AgentPlaygroundConversationCreateController
{
    public function __invoke(Request $request, Agent $agent, ChatMemory $memory)
    {
        $conversationId = $memory
            ->setAgent($agent)
            ->createPlaygroundConversation();

        $memory->setConversationId($conversationId);

        return [
            'id' => $conversationId,
        ];
    }
}
