<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;

class ChatLoginController
{
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $agent = current_agent();

        $request->authenticateForCurrentAgent($agent);

        $request->session()->regenerate();

        return redirect()->intended(route('chat.home', absolute: false));
    }
}
