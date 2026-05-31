<?php

namespace App\Auth\Login;

use App\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * Shared post-credential-verified step for sign-in paths that bypass
 * Fortify's normal login pipeline (magic links, social OAuth callbacks).
 *
 * Mirrors the session bookkeeping Fortify's RedirectIfTwoFactorAuthenticatable
 * does, so a 2FA-confirmed user is routed to the challenge screen instead of
 * being signed in by a single-factor path.
 */
class TwoFactorAwareLogin
{
    public static function redirect(User $user, string $intendedUrl, bool $remember = false): RedirectResponse
    {
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            session([
                'login.id' => $user->getAuthIdentifier(),
                'login.remember' => $remember,
                'url.intended' => $intendedUrl,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return new RedirectResponse(route('two-factor.login'));
        }

        Auth::guard('web')->login($user, $remember);

        return new RedirectResponse($intendedUrl);
    }
}
