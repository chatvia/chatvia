<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectMessengerRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's Facebook Page. Both gated by `agent.setup`.
 * Connect validates the token via {@see ConnectMessengerPageAction};
 * disconnect is a single delete, so it stays inline (the app-level webhook
 * can't be deregistered via API — Meta keeps posting to the dead URL and
 * gets silent 200s until the workspace removes it from their Meta app).
 */
class AgentMessengerChannelController
{
    public function connect(ConnectMessengerRequest $request, Agent $agent, ConnectMessengerPageAction $action): RedirectResponse
    {
        // Messenger (and messaging channels generally) is for public agents
        // only — a Messenger sender is anonymous to Chatvia and can't satisfy
        // a protected agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'page_access_token' => 'Messenger is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $page = $action->execute($agent, $request->pageAccessToken(), $request->appSecret());

        if ($page === null) {
            return back()->withErrors([
                'page_access_token' => 'Meta rejected that token. Double-check it under Messenger → API settings in your Meta app and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        $agent->messengerPage?->delete();

        return back();
    }
}
