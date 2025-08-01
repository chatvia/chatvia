<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Support\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class ChatShowLoginController
{
    /**
     * Show the member login page.
     */
    public function __invoke(Request $request): Response
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
        ]);
    }
}
