<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectInstagramRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's Instagram account. Both gated by
 * `agent.setup`. Connect validates the token via
 * {@see ConnectInstagramAccountAction}; disconnect is a single delete, so it
 * stays inline (there is no webhook to deregister — Meta keeps posting to the
 * dead URL and gets silent 200s until the workspace removes it from their
 * app).
 */
class AgentInstagramChannelController
{
    public function connect(ConnectInstagramRequest $request, Agent $agent, ConnectInstagramAccountAction $action): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        // Instagram (and messaging channels generally) is for public agents
        // only — an Instagram sender is anonymous to Chatvia and can't satisfy
        // a protected agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'access_token' => 'Instagram is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $account = $action->execute($agent, $request->accessToken(), $request->appSecret());

        if ($account === null) {
            return back()->withErrors([
                'access_token' => 'Instagram rejected that token. Generate a fresh one under Instagram → API setup with Instagram login in your Meta app and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $agent->instagramAccount?->delete();

        return back();
    }
}
