<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Models\Agent;
use App\Models\Member;
use App\Services\Auth\AgentMemberAuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChatMemberSwitchAgentController
{
    public function __invoke(Request $request, Agent $agent, AgentMemberAuthTokenService $authTokenService)
    {
        /** @var Member $member */
        $member = $request->user('member');

        if (! $member->hasAccessToAgent($agent)) {
            throw ValidationException::withMessages([
                'agent' => 'You do not have access to this agent',
            ]);
        }

        // Switch the member's agent
        $authToken = $authTokenService->generateToken($agent, $member, 5);

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
