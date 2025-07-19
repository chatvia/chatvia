<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentMembersAccessGrantController
{
    public function __invoke(Agent $agent, Request $request)
    {
        $request->validate([
            'member' => ['required', 'exists:members,id'],
        ]);

        $agent->members()->attach($request->input('member'));
    }
}
