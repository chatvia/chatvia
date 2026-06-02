<?php

namespace App\Providers;

use App\Workspace\Actions\Fortify\ResetUserPassword;
use App\Workspace\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

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
        $this->registerAuthPipeline();
        $this->registerViews();
        $this->registerRateLimiters();
    }

    private function registerAuthPipeline(): void
    {
        Fortify::authenticateThrough(fn () => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /**
     * Single source of truth for credential validation. Runs inside the
     * default Fortify login pipeline (before the 2FA short-circuit), so the
     * 2FA challenge still kicks in for users that have it enabled — we just
     * return the user and let Fortify own the session login + redirect.
     */
    private function registerAuthCallback(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', $request->string('email')->toString())->first();

            if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
                return null;
            }

            $user->update(['last_login_at' => now()]);

            return $user;
        });
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => true,
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
        RateLimiter::for('login', function (Request $request) {
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
        RateLimiter::for('two-factor', function (Request $request) {
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
