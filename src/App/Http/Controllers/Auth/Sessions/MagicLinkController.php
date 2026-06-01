<?php

namespace App\Http\Controllers\Auth\Sessions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\Auth\Actions\Sessions\SendMagicLinkAction;
use Illuminate\Http\RedirectResponse;

class MagicLinkController extends Controller
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
