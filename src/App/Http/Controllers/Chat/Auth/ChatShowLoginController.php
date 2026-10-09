<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sign-in page of a protected agent's hosted chat.
 */
class ChatShowLoginController
{
    /**
     * Show the member login page.
     */
    public function __invoke(Request $request, #[HostedChatAgent] ?Agent $agent): RedirectResponse|Response
    {
        if (is_null($agent) || $agent->is_public) {
            return to_route('chat.home');
        }

        // Guest page of a protected agent: send only what the login screen
        // shows. Never the Agent model itself — it carries the system prompt
        // and owner ids.
        return Inertia::render('auth/LoginChatter', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'agentName' => $agent->name,
            'appearance' => $agent->settings('appearance'),
        ]);
    }
}
