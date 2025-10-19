<?php

namespace App\Http\Controllers\Chat\Auth;

use Illuminate\Http\Request;

class ChatMemberAgentsController
{
    public function __invoke(Request $request)
    {
        return $request
            ->user('member')
            ->agents()
            ->select(['agents.id', 'agents.name', 'agents.sub_domain'])
            ->get();
    }
}
