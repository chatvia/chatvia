<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentMemberInvitationSendController
{
    public function __invoke(Agent $agent, Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
            ],
        ]);

        $email = $request->input('email');

        $agentMemberExists = $agent->members()->where('email', $email)->exists();

        if ($agentMemberExists) {
            return response();
        }

        $user = $request->user();

        /** @var Member $member */
        $member = $user->members()->where('email', $email)->first();

        if (! $member) {
            /** @var Member $member */
            $member = Member::create([
                'user_id' => $request->user()->id,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make(Str::random(8)),
                'verification_token' => Str::uuid()->toString(),
            ]);
        }

        $agent->members()->attach($member->id);

        $member->invite($agent);
    }
}
