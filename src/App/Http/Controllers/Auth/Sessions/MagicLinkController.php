<?php

namespace App\Http\Controllers\Auth\Sessions;

use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\Identity\Workspace\Actions\Sessions\SendMagicLinkAction;
use Illuminate\Http\RedirectResponse;

/**
 * Emails a one-time sign-in link to the dashboard. The answer is the same
 * whether or not the email has an account, so the form cannot be used to find
 * out who is registered.
 */
class MagicLinkController
{
    public function store(SendMagicLinkRequest $request, SendMagicLinkAction $sendLink): RedirectResponse
    {
        $sendLink->execute(
            data: $request->toData(),
            redirectUrl: route('dashboard'),
        );

        return back()->with('status', __('If an account matches this email, a sign-in link is on its way.'));
    }
}
