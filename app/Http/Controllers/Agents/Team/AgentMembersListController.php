<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;

class AgentMembersListController
{
    public function __invoke(Agent $agent, Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $members = $user->members()->with('agents')->get();

        return $members->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'invitation_accepted_at' => $member->invitation_accepted_at,
                'agents' => $member->agents->pluck('url', 'id')->toArray(),
            ];
        });

        return $agent->members()->get()->collect()->map(function ($member) {
            return $member->only(['id', 'name', 'email', 'invitation_accepted_at']);
        });
    }
}
