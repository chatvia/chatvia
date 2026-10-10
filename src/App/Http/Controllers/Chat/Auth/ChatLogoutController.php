<?php

namespace App\Http\Controllers\Chat\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the chatter out of the hosted chat.
 */
class ChatLogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('chat.login.show');
    }
}
