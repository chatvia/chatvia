<?php

namespace App\Http\Controllers\Chat\Auth;

use Domain\Agent\Services\AgentLocator;
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
        $agent = AgentLocator::resolveAgentFromSubdomain($request->agentSlug());

        if (is_null($agent) || $agent->is_public) {
            return to_route('chat.home');
        }

        return Inertia::render('auth/LoginChatter', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'agent' => $agent,
            'agentName' => $agent->name ?? null,
            'appearance' => current_agent()?->settings('appearance'),
        ]);
    }
}
