<?php

namespace App\Identity\Workspace\Social;

use App\Identity\Workspace\Actions\Social\LinkSocialAccountAction;
use App\Identity\Workspace\Actions\Social\SocialLoginAction;
use App\Identity\Workspace\Exceptions\SocialAccountAlreadyLinkedException;
use App\Identity\Workspace\Login\TwoFactorAwareLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Support\Enums\User\SocialProvider;

/**
 * Shared post-OAuth landing logic for /auth/{provider}/callback. Branches on
 * a session marker set by `SocialController::connect()` so the same callback
 * URL (the one registered with the OAuth provider) handles both:
 *
 *   1. Sign-in       — guest hits "Sign in with Google" → SocialLoginAction
 *      logs them in (then TwoFactorAwareLogin handles the 2FA gate).
 *   2. Account link  — authenticated user clicks Connect on /settings/social →
 *      LinkSocialAccountAction adds the row and bounces back to settings.
 */
class SocialOauthCallback
{
    public function __construct(
        private readonly SocialLoginAction $login,
        private readonly LinkSocialAccountAction $link,
    ) {}

    public function handle(SocialProvider $provider): RedirectResponse
    {
        if (session()->pull('social.intent') === 'link' && Auth::check()) {
            return $this->handleLink($provider);
        }

        return $this->handleLogin($provider);
    }

    private function handleLink(SocialProvider $provider): RedirectResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider->value);
        $socialUser = $driver->user();

        try {
            $this->link->execute(Auth::user(), $provider, (string) $socialUser->getId());
        } catch (SocialAccountAlreadyLinkedException $e) {
            return to_route('account.social.edit')->withErrors(['provider' => $e->getMessage()]);
        }

        return to_route('account.social.edit')
            ->with('success', ucfirst($provider->value).' connected.');
    }

    private function handleLogin(SocialProvider $provider): RedirectResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider->value);
        $socialUser = $driver->user();

        $user = $this->login->execute(
            $provider,
            (string) $socialUser->getId(),
            $socialUser->getEmail(),
            $this->providerEmailVerified($socialUser),
        );

        if (! $user) {
            return to_route('login')->with('error', 'No account found!');
        }

        return TwoFactorAwareLogin::redirect($user, route('dashboard'));
    }

    /**
     * Whether the OAuth provider asserted the email is verified: true / false
     * when the claim is present, null when the provider did not include it (the
     * action then keeps the prior trust-the-provider behaviour). Reads the
     * common OIDC `email_verified` / Google `verified_email` raw claims.
     */
    private function providerEmailVerified(User $socialUser): ?bool
    {
        if (! $socialUser instanceof AbstractUser) {
            return null;
        }

        $raw = (array) $socialUser->getRaw();

        foreach (['email_verified', 'verified_email'] as $claim) {
            if (array_key_exists($claim, $raw)) {
                return filter_var($raw[$claim], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return null;
    }
}
