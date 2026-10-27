<?php

namespace App\Http\Auth;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokens;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;

/**
 * The last step of the sign-in paths that do not go through Fortify's login
 * pipeline (magic links, social sign-in): the credential
 * is already verified, and this decides how the session starts.
 *
 *  - A deactivated account is sent back to the login page.
 *  - An account with confirmed two-factor authentication is NOT signed in: the
 *    pending login is stored in the session the same way Fortify does it, and
 *    the user is sent to the two-factor challenge.
 *  - Anyone else is signed in on the `web` guard and sent to the intended URL.
 *
 * A sign-in for a hosted chat ({@see respondForChat()} for staff,
 * {@see respondForChatter()} for chatters) never opens a dashboard session:
 * the chat has its own.
 */
final readonly class TwoFactorAwareSignIn
{
    public function __construct(
        private AuthFactory $auth,
        private ChatterAuthTokens $chatTokens,
    ) {}

    public function respond(User $user, string $intendedUrl, bool $remember = false): RedirectResponse
    {
        if ($user->isDeactivated()) {
            return redirect()->route('login')->with('error', __('Your account has been deactivated.'));
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            session([
                'login.id' => $user->getAuthIdentifier(),
                'login.remember' => $remember,
                'url.intended' => $intendedUrl,
            ]);

            return redirect()->route('two-factor.login');
        }

        $this->auth->guard('web')->login($user, $remember);

        return redirect()->to($intendedUrl);
    }

    /**
     * The same decision for a sign-in to $agent's hosted chat. Without
     * two-factor authentication the user goes straight into the chat with a
     * one-time token. With it, the challenge runs first (Fortify's, on the
     * dashboard session) and then lands on the chat hand-off route, which
     * moves the sign-in into the chat.
     */
    public function respondForChat(User $user, Agent $agent): RedirectResponse
    {
        $slug = $agent->slug ?? $agent->external_id;

        if ($user->isDeactivated()) {
            return redirect(route('chat.login.show', ['agentSlug' => $slug], absolute: false))
                ->with('error', __('Your account has been deactivated.'));
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            session([
                'login.id' => $user->getAuthIdentifier(),
                'login.remember' => false,
                'url.intended' => route('chat-handoff', ['agentSlug' => $slug], absolute: false),
            ]);

            return redirect()->route('two-factor.login');
        }

        $token = $this->chatTokens->generateToken($agent, $user, 5);

        return redirect(route('chat.home', ['agentSlug' => $slug, 'auth_token' => $token], absolute: false));
    }

    /**
     * A chatter's sign-in to $agent's hosted chat. Chatters have no two-factor
     * step, so a one-time token takes them straight into the chat.
     */
    public function respondForChatter(Chatter $chatter, Agent $agent): RedirectResponse
    {
        $slug = $agent->slug ?? $agent->external_id;

        if ($chatter->isDeactivated()) {
            return redirect(route('chat.login.show', ['agentSlug' => $slug], absolute: false))
                ->with('error', __('Your account has been deactivated.'));
        }

        $token = $this->chatTokens->generateToken($agent, $chatter, 5);

        return redirect(route('chat.home', ['agentSlug' => $slug, 'auth_token' => $token], absolute: false));
    }
}
