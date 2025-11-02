<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Services\Agents\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ChatShowLoginController
{
    /**
     * Show the member login page.
     */
    public function __invoke(Request $request)
    {
        $agent = Agent::resolveAgentFromSubdomain($request->subdomain());

        if ($agent->is_public) {
            return to_route('chat.home');
        }

        return Inertia::render('auth/LoginMember', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'agent' => $agent,
            'agentName' => $agent->name ?? null,
            'appearance' => current_agent()?->settings('appearance'),
        ]);
    }
}
