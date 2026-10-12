<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatViewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signs the viewer (chatter or staff) out of the hosted chat.
 */
class ChatLogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        HostedChatViewer::signOut();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('chat.login.show');
    }
}
