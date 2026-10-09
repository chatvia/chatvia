<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Requests\Auth\LoginRequest;
use App\Identity\Workspace\Actions\Sessions\LoginUserToAgentAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signs a chatter in to a protected agent's hosted chat with a password.
 */
class ChatLoginController
{
    public function __invoke(
        LoginRequest $request,
        #[HostedChatAgent] Agent $agent,
        LoginUserToAgentAction $loginToAgent,
    ): RedirectResponse {
        $user = $loginToAgent->execute($request->toData(), $agent);

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
