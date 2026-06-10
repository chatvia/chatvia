<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectSlackRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Actions\ConnectSlackAppAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's Slack app. Both gated by `agent.setup`.
 * Connect validates the token via {@see ConnectSlackAppAction}; disconnect is
 * a single delete, so it stays inline (there is no webhook to deregister —
 * Slack keeps posting to the dead URL and gets silent 200s until the
 * workspace removes it from their app).
 */
class AgentSlackChannelController
{
    public function connect(ConnectSlackRequest $request, Agent $agent, ConnectSlackAppAction $action): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        // Slack (and messaging channels generally) is for public agents only —
        // a Slack sender is anonymous to Chatvia and can't satisfy a protected
        // agent's access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'bot_token' => 'Slack is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $app = $action->execute($agent, $request->botToken(), $request->signingSecret());

        if ($app === null) {
            return back()->withErrors([
                'bot_token' => 'Slack rejected that token. Double-check it under OAuth & Permissions in your Slack app and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $agent->slackApp?->delete();

        return back();
    }
}
