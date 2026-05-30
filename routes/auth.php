<?php

use App\Http\Controllers\Auth\Email\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\Email\EmailVerificationPromptController;
use App\Http\Controllers\Auth\Email\VerifyEmailController;
use App\Http\Controllers\Auth\Password\ConfirmablePasswordController;
use App\Http\Controllers\Auth\Password\NewPasswordController;
use App\Http\Controllers\Auth\Password\PasswordResetLinkController;
use App\Http\Controllers\Auth\Registration\RegisteredUserController;
use App\Http\Controllers\Auth\Sessions\AuthenticatedSessionController;
use App\Http\Controllers\Agents\Invitations\AcceptAgentInvitationController;
use App\Http\Controllers\Agents\Invitations\AgentInvitationController;
use App\Http\Controllers\Agents\Invitations\AgentInvitationSocialCallbackController;
use App\Http\Controllers\Agents\Invitations\AgentInvitationSocialStartController;
use App\Http\Controllers\Teams\Invitations\AcceptTeamInvitationController;
use App\Http\Controllers\Teams\Invitations\TeamInvitationController;
use Illuminate\Support\Facades\Route;

// Team invitation routes (accessible via signed URL)
Route::get('invitation/{invitation}', [TeamInvitationController::class, 'show'])
    ->middleware('signed')
    ->name('teams.invitation.show');

Route::post('invitation/{invitation}/accept', AcceptTeamInvitationController::class)
    ->name('teams.invitation.accept');

// Agent member invitations. The `{token}` segment is a high-entropy random
// string stored on `chatter_invitations` and looked up at the controller — it
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

Route::middleware('guest')->group(function (): void {
    //    Route::get('register', [RegisteredUserController::class, 'create'])
    //        ->name('register');
    //
    //    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
