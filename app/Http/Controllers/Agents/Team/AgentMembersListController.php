<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;

class AgentMembersListController
{
    public function __invoke(Agent $agent, Request $request)
    {
        if (! $agent->isStandalone()) {
            return [];
        }

        /** @var User $user */
        $user = $request->user();

        abort_if(
            boolean: $user->cannot('view', $agent),
            code: 404,
        );

        return $user->members()->with('agents')->get()->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'email_verified_at' => $member->email_verified_at,
                'agents' => $member->agents->pluck('url', 'id')->toArray(),
            ];
        });
    }
}
