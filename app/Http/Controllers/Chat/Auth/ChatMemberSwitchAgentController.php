<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChatMemberSwitchAgentController
{
    public function __invoke(Request $request, Agent $agent)
    {
        /** @var Member $member */
        $member = $request->user('member');

        if (! $member->hasAccessToAgent($agent)) {
            throw ValidationException::withMessages([
                'agent' => 'You do not have access to this agent',
            ]);
        }

        // Switch the member's agent'
        $authToken = Str::uuid()->toString().'-'.$agent->id.'-'.$member->id;

        Cache::put(
            key: $authToken,
            value: [
                'member_id' => $member->id,
                'agent_id' => $agent->id,
            ],
            ttl: now()->addMinutes(5)
        );

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
