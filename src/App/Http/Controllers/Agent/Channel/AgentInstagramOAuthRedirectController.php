<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\InteractsWithMetaOAuth;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;

/**
 * First step of connecting an agent's Instagram professional account through
 * Facebook Login for Business: stash the pending connection in the session and
 * bounce to Meta's consent screen. Gated by `agent.permission:agent.channels`;
 * the rest of the flow is {@see AgentInstagramOAuthCallbackController} and
 * {@see AgentInstagramChannelController}.
 */
class AgentInstagramOAuthRedirectController
{
    use InteractsWithMetaOAuth;

    public function __invoke(Agent $agent, MetaOAuthClient $oauth): RedirectResponse
    {
        return $this->startMetaOAuth($agent, MetaOAuthChannel::Instagram, $oauth);
    }
}
