<?php

namespace App\Providers;

use App\Identity\Workspace\Fortify\ResetUserPassword;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

/**
 * Fortify wiring: who may sign in with a password or passkey (no chatters, no
 * deactivated accounts), the password-reset step, the auth pages, and the
 * login, two-factor and passkey rate limits.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerAuthCallback();
        $this->registerPasskeyLoginGate();
        $this->registerViews();
        $this->registerRateLimiters();
    }

    /**
     * Single source of truth for credential validation. Runs inside the
     * default Fortify login pipeline (before the 2FA short-circuit), so the
     * 2FA challenge still kicks in for users that have it enabled — we just
     * return the user and let Fortify own the session login + redirect.
     *
     * Chatters sign in on the hosted chat (/chat/login), never here, and a
     * deactivated account can't sign in at all.
     */
    private function registerAuthCallback(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = app(PasswordAuthenticator::class)->findUserByCredentials(
                $request->string(Fortify::username())->toString(),
                $request->string('password')->toString(),
            );

            if ($user === null || $user->isChatter() || $user->isDeactivated()) {
                return null;
            }

            return $user;
        });
    }

    /**
     * A passkey proves who the user is, but the same account rules as the
     * password login apply: no chatters, no deactivated accounts.
     */
    private function registerPasskeyLoginGate(): void
    {
        Passkeys::authorizeLoginUsing(
            fn (Request $request, PasskeyUser $user): bool => $user instanceof User
                && ! $user->isChatter()
                && ! $user->isDeactivated(),
        );
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => true,
            // Shows the "Create an account" link when self-serve sign-up is open.
            'canRegister' => (bool) config('billing.registration_enabled', false),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
    }

    private function registerRateLimiters(): void
    {
        // Login: defense in depth.
        //   - 5/min per (lowercase email, IP) — short-term shield against
        //     simple credential stuffing on a single account.
        //   - 30/hour per IP — long-term cap against a botnet rotating
        //     emails from one IP. Slow attacks still fall under this ceiling.
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower((string) $request->input(Fortify::username()));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by(Str::transliterate($email.'|'.$ip)),
                Limit::perHour(30)->by($ip),
            ];
        });

        // Two-factor challenge: keyed by the pending-login session id so a
        // single user can't hammer codes. 5/min plus a 20/hour ceiling stops
        // patient TOTP guessing without locking out fat fingers.
        RateLimiter::for('two-factor', function (Request $request): array {
            $key = (string) $request->session()->get('login.id');

            return [
                Limit::perMinute(5)->by($key),
                Limit::perHour(20)->by($key),
            ];
        });

        // Passkeys can't be guessed (asymmetric crypto), so this is mainly
        // DoS protection on the WebAuthn endpoints.
        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
