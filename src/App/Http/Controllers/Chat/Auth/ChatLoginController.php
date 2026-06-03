<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Identity\Workspace\Actions\Sessions\LoginUserToAgentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ChatLoginController
{
    public function __invoke(LoginRequest $request, LoginUserToAgentAction $loginToAgent): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $loginToAgent->execute($request->toData(), current_agent());

        if (! $user) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        $request->session()->regenerate();

        return redirect()->intended(route('chat.home', absolute: false));
    }
}
