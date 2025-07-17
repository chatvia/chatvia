<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentMembersListController
{
    public function __invoke(Agent $agent, Request $request)
    {
        return $agent->members()->get()->collect()->map(function ($member) {
            return $member->only(['id', 'name', 'email', 'invitation_accepted_at']);
        });
    }
}
