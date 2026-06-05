<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Overrides Fortify's `password.email` handler so we never leak whether an
 * email exists in our database — the response is identical for valid and
 * unknown addresses. The reset link itself is only sent when the address
 * resolves to a real user, but the user never sees the difference.
 *
 * Throttle is applied at the route level — see routes/auth.php — so abusive
 * callers hit a 429 instead of being able to enumerate accounts by timing.
 */
class ForgotPasswordController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Fire-and-forget: we don't surface INVALID_USER / PASSWORD_RESET errors
        // to the client. Password::sendResetLink internally no-ops for unknown
        // emails, so the only side-effect skipped here is the error response.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
