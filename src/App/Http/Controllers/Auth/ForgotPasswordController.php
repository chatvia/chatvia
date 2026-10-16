<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Identity\Workspace\Actions\Account\SendPasswordResetLinkAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

/**
 * Overrides Fortify's `password.email` handler so we never leak whether an
 * email exists in our database — the response is identical for valid and
 * unknown addresses ({@see SendPasswordResetLinkAction}).
 *
 * Throttle is applied at the route level — see routes/auth.php — so abusive
 * callers hit a 429 instead of being able to enumerate accounts by timing.
 */
class ForgotPasswordController
{
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetLinkAction $sendResetLink): RedirectResponse
    {
        $sendResetLink->execute($request->emailAddress());

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
