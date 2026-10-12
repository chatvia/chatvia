<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Http\Requests\Auth\LoginRequest;
use App\Identity\Chatter\Actions\LoginChatterToAgentAction;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signs a chatter in to a protected agent's hosted chat with a password.
 * After {@see self::MAX_ATTEMPTS} failures for one workspace, email and IP the
 * form refuses further tries until the counter expires.
 */
class ChatLoginController
{
    private const int MAX_ATTEMPTS = 5;

    public function __invoke(
        LoginRequest $request,
        #[HostedChatAgent] Agent $agent,
        LoginChatterToAgentAction $loginToAgent,
    ): RedirectResponse {
        $key = $request->throttleKey($agent);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $data = $request->toData();
        $chatter = $loginToAgent->execute($data, $agent);

        if (! $chatter instanceof Chatter) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        HostedChatViewer::signIn($chatter, $data->remember);

        $request->session()->regenerate();

        return redirect()->intended(route('chat.home', absolute: false));
    }
}
