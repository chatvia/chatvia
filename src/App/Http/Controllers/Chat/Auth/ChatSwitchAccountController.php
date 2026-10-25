<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Http\Chat\PendingChatSignIn;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Continue as …?" step of a hosted chat: a sign-in link was opened in a
 * browser already signed in to the chat as someone else
 * ({@see PendingChatSignIn}). Showing the page changes nothing; the switch
 * happens only on the CSRF-protected POST, and "stay" drops the link.
 */
class ChatSwitchAccountController
{
    public function show(Request $request, #[HostedChatAgent] Agent $agent): RedirectResponse|Response
    {
        $pending = PendingChatSignIn::find($request, $agent);

        if (! $pending instanceof PendingChatSignIn) {
            return to_route('chat.home');
        }

        $current = HostedChatViewer::account();

        // Only what the page shows: the two names and the link account's email.
        return Inertia::render('auth/ConfirmChatSwitch', [
            'agentName' => $agent->name,
            'appearance' => $agent->settings('appearance'),
            'currentName' => $current?->name,
            'nextName' => $pending->account->name,
            'nextEmail' => $pending->account->email,
        ]);
    }

    public function store(Request $request, #[HostedChatAgent] Agent $agent): RedirectResponse
    {
        $pending = PendingChatSignIn::find($request, $agent);

        if (! $pending instanceof PendingChatSignIn) {
            return to_route('chat.home');
        }

        // A clean session: the new account must not pick up the previous
        // one's chat sessions or anything else kept for it.
        HostedChatViewer::signOut();
        $request->session()->invalidate();

        HostedChatViewer::signIn($pending->account);
        $request->session()->regenerateToken();

        return redirect()->to(filled($pending->returnTo) ? $pending->returnTo : route('chat.home', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        PendingChatSignIn::forget($request);

        return to_route('chat.home');
    }
}
