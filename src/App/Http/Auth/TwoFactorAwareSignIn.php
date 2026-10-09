<?php

namespace App\Http\Auth;

use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;

/**
 * The last step of the sign-in paths that do not go through Fortify's login
 * pipeline (magic links, social sign-in, the trial checkout): the credential
 * is already verified, and this decides how the session starts.
 *
 *  - A deactivated account is sent back to the login page.
 *  - An account with confirmed two-factor authentication is NOT signed in: the
 *    pending login is stored in the session the same way Fortify does it, and
 *    the user is sent to the two-factor challenge.
 *  - Anyone else is signed in on the `web` guard and sent to the intended URL.
 */
final readonly class TwoFactorAwareSignIn
{
    public function __construct(
        private AuthFactory $auth,
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
}
