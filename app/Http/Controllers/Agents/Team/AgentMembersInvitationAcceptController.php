<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AgentMembersInvitationAcceptController
{
    public function __invoke(Request $request, Agent $agent, string $token)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $member = Member::query()
            ->where('verification_token', $token)
            ->firstOrFail();

        $member->update(
            [
                'name' => $request->input('name'),
                'password' => Hash::make($request->input('password')),
                'verification_token' => null,
                'invitation_accepted_at' => now(),
            ]
        );

        return Inertia::location($agent->chat_url);
    }
}
