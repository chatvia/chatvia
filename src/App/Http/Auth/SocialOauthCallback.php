<?php

namespace App\Http\Auth;

use App\Identity\Workspace\Actions\Social\LinkSocialAccountAction;
use App\Identity\Workspace\Actions\Social\SocialLoginAction;
use App\Identity\Workspace\Exceptions\SocialAccountAlreadyLinkedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Support\Enums\User\SocialProvider;

/**
 * The landing of /auth/{provider}/callback, shared by every social provider.
 * It branches on a session marker set by `SocialController::connect()`, so the
 * one callback URL registered with the provider handles both:
 *
 *   1. Sign-in      — a guest clicked "Sign in with Google": SocialLoginAction
 *      finds the account and {@see TwoFactorAwareSignIn} starts the session
 *      (or the two-factor challenge).
 *   2. Account link — a signed-in user clicked Connect in their settings:
 *      LinkSocialAccountAction adds the identity and the user goes back to
 *      the settings page.
 */
final readonly class SocialOauthCallback
{
    public function __construct(
        private SocialLoginAction $login,
        private LinkSocialAccountAction $link,
        private TwoFactorAwareSignIn $signIn,
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
            ->with('success', __(':provider connected.', ['provider' => ucfirst($provider->value)]));
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
            return to_route('login')->with('error', __('No account found!'));
        }

        return $this->signIn->respond($user, route('dashboard'));
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
