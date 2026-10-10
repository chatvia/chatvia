<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Enums\MetaOAuthChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Meta's return trip after the Facebook Page consent: verify the returned
 * state against the session and hold the granted accounts for the picker. The
 * route sits outside the `{agent}` group (Meta whitelists one fixed redirect
 * URI), so the session state is what binds it to the agent.
 */
class AgentMessengerOAuthCallbackController
{
    use InteractsWithMetaOAuth;

    public function __invoke(Request $request, CompleteMetaOAuthAction $complete): RedirectResponse
    {
        return $this->completeMetaOAuth($request, MetaOAuthChannel::Messenger, $complete);
    }
}
