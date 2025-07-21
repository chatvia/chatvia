<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\Member;
use Inertia\Inertia;

class AgentMembersInvitationCreateAccountController
{
    public function __invoke(Agent $agent, string $token)
    {

        /** @var Member $member */
        $member = Member::query()
            ->select(['verification_token', 'name', 'email'])
            ->where('verification_token', $token)
            ->firstOrFail();

        return Inertia::render('agents/MemberPassword', [
            'agent' => $agent->id,
            'member' => $member->toArray(),
            'token' => $token,
        ]);
    }
}
