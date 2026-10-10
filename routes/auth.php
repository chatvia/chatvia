<?php

declare(strict_types=1);

use App\Http\Controllers\Agent\Invitations\AcceptAgentInvitationController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationSocialCallbackController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationSocialStartController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\Registration\RegisteredUserController;
use App\Http\Controllers\Auth\Sessions\MagicLinkController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Team\Invitations\AcceptTeamInvitationController;
use App\Http\Controllers\Team\Invitations\TeamInvitationController;
use App\Http\Middleware\Auth\EnsureRegistrationIsOpen;
use Illuminate\Support\Facades\Route;

// Team invitation routes (accessible via signed URL). The {invitation} segment
// binds by bigint id; a non-integer (e.g. scanner probes for `.env`) resolves to
// null in Invitation::resolveRouteBinding(), so ->missing() redirects to login
// instead of hitting Postgres and 500ing.
Route::get('invitation/{invitation}', [TeamInvitationController::class, 'show'])
    ->middleware('signed')
    ->missing(fn () => redirect()->route('login'))
    ->name('teams.invitation.show');

Route::post('invitation/{invitation}/accept', AcceptTeamInvitationController::class)
    ->middleware('signed')
    ->missing(fn () => redirect()->route('login'))
    ->name('teams.invitation.accept');

// Agent member invitations. The `{token}` segment is a high-entropy random
// string stored on `agent_chatter_invitations` and looked up at the controller — it
// replaces Laravel's signed-URL approach. The row is deleted on accept, so
// the link is single-use. Expiry is checked in the controller.
Route::get('chat-invitation/{token}', AgentInvitationController::class)
    ->name('agents.invitation.show');

Route::post('chat-invitation/{token}/accept', AcceptAgentInvitationController::class)
    ->name('agents.invitation.accept');

Route::get('chat-invitation/{token}/oauth/{provider}', AgentInvitationSocialStartController::class)
    ->name('agents.invitation.social.start');

// The OAuth provider redirects back here after the user signs in there; the
// pending invitation reference is read from the session.
Route::get('chat-invitation/oauth/{provider}/callback', AgentInvitationSocialCallbackController::class)
    ->name('agents.invitation.social.callback');

// Login / forgot-password / reset / verify-email / confirm-password / logout /
// two-factor / passkey routes are registered by Laravel Fortify (sign-up is
// below, not Fortify's registration feature). See
// config/fortify.php for enabled features and FortifyServiceProvider for the
// view callbacks and the authenticateUsing closure.

// First-time password setup for accounts created by the trial-checkout
// webhook (provisioned with a random password that is never emailed).
// RequirePasswordSetup — in the web group — funnels flagged users here.
Route::middleware('auth')->group(function (): void {
    Route::get('set-password', [SetPasswordController::class, 'show'])
        ->name('password.setup');

    Route::post('set-password', [SetPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.setup.store');
});

// Self-serve sign-up. Closed on the hosted product, where the Stripe trial
// checkout creates accounts; open by default on a self-hosted install without
// billing. The "Open sign-up" option in the admin panel (Settings → Billing)
// decides; a closed form answers 404.
Route::middleware(['guest', EnsureRegistrationIsOpen::class])->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1,register')
        ->name('register.store');
});

Route::middleware('guest')->group(function (): void {
    Route::post('magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('magic-link.send');

    // Overrides Fortify's auto-registered POST /forgot-password. The wrapper
    // never leaks whether the email exists in our DB, and a tight per-IP
    // throttle caps account-enumeration attempts. Re-using `password.email`
    // means every existing call site (route name + URL) keeps working.
    Route::post('forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:5,1')
        ->name('password.email');
});
