<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Http\Requests\Auth\LoginRequest;
use App\Identity\Chatter\Actions\LoginChatterToAgentAction;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Signs a chatter in to a protected agent's hosted chat with a password.
 * Abusive tries are stopped by the route's named `chat-login` limiter
 * (per IP, and per workspace + email + IP).
 */
class ChatLoginController
{
    public function __invoke(
        LoginRequest $request,
        #[HostedChatAgent] Agent $agent,
        LoginChatterToAgentAction $loginToAgent,
    ): RedirectResponse {
        $data = $request->toData();
        $chatter = $loginToAgent->execute($data, $agent);

        if (! $chatter instanceof Chatter) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        HostedChatViewer::signIn($chatter, $data->remember);

        $request->session()->regenerate();

        return redirect()->intended(route('chat.home', absolute: false));
    }
}
