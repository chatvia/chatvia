<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Http\Controllers\Settings\Chatter\Concerns\ResolvesWorkspaceChatters;
use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends a workspace chatter a password-reset link (Settings → Members). Gated
 * by `account.chatters.manage`; the chatter must belong to the workspace.
 */
class ResetChatterPasswordController
{
    use ResolvesWorkspaceChatters;

    public function __invoke(Request $request, string $chatter, ResetChatterPasswordAction $reset): RedirectResponse
    {
        $member = $this->findChatter($request, $chatter);

        $reset->execute($member);

        return back()->with('success', __('Password reset link sent to :email.', ['email' => $member->email]));
    }
}
