<?php

namespace App\Http\Controllers\Agents\Team;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentMemberInvitationSendController
{
    public function __invoke(Agent $agent, Request $request)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

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

        $authUser = $request->user();

        if ($authUser->email === $email) {
            throw ValidationException::withMessages([
                'email' => ['You cannot invite yourself'],
            ]);
        }

        /** @var Member $member */
        $member = $authUser->members()->where('email', $email)->first();

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
