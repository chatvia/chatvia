<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectTeamsRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Actions\ConnectTeamsBotAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's Microsoft Teams bot. Both gated by
 * `agent.setup`. Connect validates the credentials via
 * {@see ConnectTeamsBotAction}; disconnect is a single delete, so it stays
 * inline (there is no webhook to deregister — the connector keeps posting to
 * the dead URL and gets silent 200s until the workspace removes the endpoint
 * from their Azure bot).
 */
class AgentTeamsChannelController
{
    public function connect(ConnectTeamsRequest $request, Agent $agent, ConnectTeamsBotAction $action): RedirectResponse
    {
        // Teams (and messaging channels generally) is for public agents only —
        // a Teams sender is anonymous to Chatvia and can't satisfy a protected
        // agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'app_id' => 'Microsoft Teams is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $bot = $action->execute($agent, $request->appId(), $request->appSecret(), $request->tenantId());

        if ($bot === null) {
            return back()->withErrors([
                'app_secret' => 'Microsoft rejected those credentials. Double-check the App ID, client secret, and tenant ID in your Azure bot and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        $agent->teamsBot?->delete();

        return back();
    }
}
