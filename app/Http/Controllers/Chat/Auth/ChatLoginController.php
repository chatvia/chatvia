<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Auth\MemberLoginRequest;
use Illuminate\Http\RedirectResponse;

class ChatLoginController
{
    public function __invoke(MemberLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('chat.home', absolute: false));
    }
}
