<?php

namespace App\Http\Controllers\Chat\Auth;

use Illuminate\Http\Request;

class ChatMemberAgentsController
{
    public function __invoke(Request $request)
    {
        return $request
            ->user()
            ->agents()
            ->where('agents.type', 'knowledge_base')
            ->select(['agents.id', 'agents.name', 'agents.sub_domain'])
            ->get();
    }
}
