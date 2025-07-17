<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentMembersDeleteController
{
    public function __invoke(Agent $agent, Request $request)
    {
        $request->validate([
            'member' => ['required', 'exists:members,id'],
            'complete' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $complete = (bool) $request->input('complete');
        $member = (int) $request->input('member');

        if ($complete) {
            $user->members()->where('id', $member)->delete();
        } else {
            $agent->members()->detach($member);
        }
    }
}
