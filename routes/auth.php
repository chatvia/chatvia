<?php

use App\Http\Controllers\Agent\Invitations\AcceptAgentInvitationController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationSocialCallbackController;
use App\Http\Controllers\Agent\Invitations\AgentInvitationSocialStartController;
use App\Http\Controllers\Auth\Sessions\MagicLinkController;
use App\Http\Controllers\Team\Invitations\AcceptTeamInvitationController;
use App\Http\Controllers\Team\Invitations\TeamInvitationController;
use Illuminate\Support\Facades\Route;

// Team invitation routes (accessible via signed URL)
Route::get('invitation/{invitation}', [TeamInvitationController::class, 'show'])
    ->middleware('signed')
    ->name('teams.invitation.show');

Route::post('invitation/{invitation}/accept', AcceptTeamInvitationController::class)
    ->name('teams.invitation.accept');

// Agent member invitations. The `{token}` segment is a high-entropy random
// string stored on `agent_member_invitations` and looked up at the controller — it
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

// Login / register / forgot-password / reset / verify-email / confirm-password /
// logout / two-factor / passkey routes are registered by Laravel Fortify. See
// config/fortify.php for enabled features and FortifyServiceProvider for the
// view callbacks and the authenticateUsing closure.

Route::middleware('guest')->group(function (): void {
    Route::post('magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('magic-link.send');
});
