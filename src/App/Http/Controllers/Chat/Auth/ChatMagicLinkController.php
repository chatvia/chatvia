<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\Auth\Actions\Sessions\SendMagicLinkAction;
use Illuminate\Http\RedirectResponse;

class ChatMagicLinkController
{
    public function __invoke(SendMagicLinkRequest $request, SendMagicLinkAction $sendLink): RedirectResponse
    {
        $sendLink->execute(
            data: $request->toData(),
            redirectUrl: route('chat.home'),
            agent: current_agent(),
        );

        return back()->with('status', __('If you have access to this chat, a sign-in link is on its way.'));
    }
}
