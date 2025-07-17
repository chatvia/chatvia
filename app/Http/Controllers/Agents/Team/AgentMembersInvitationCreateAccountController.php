<?php

namespace App\Http\Controllers\Agents\Team;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Member;
use Inertia\Inertia;

class AgentMembersInvitationCreateAccountController extends Controller
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
            'token' => $token,
            'member' => $member->toArray(),
        ]);
    }
}
