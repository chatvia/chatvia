<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Http\RedirectResponse;

/**
 * Sends a workspace chatter a password-reset link (Settings → Members). Gated
 * by `account.chatters.manage`; `workspace.scope` 404s a bound chatter of
 * another workspace.
 */
class ResetChatterPasswordController
{
    public function __invoke(Chatter $chatter, ResetChatterPasswordAction $reset): RedirectResponse
    {
        $reset->execute($chatter);

        return back()->with('success', __('Password reset link sent to :email.', ['email' => $chatter->email]));
    }
}
