<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\ExplainsChannelConnectFailures;
use App\Http\Requests\Agent\Channel\ConnectSlackRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Slack\Actions\ConnectSlackAppAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connect / disconnect an agent's Slack app. Both gated by
 * `agent.permission:agent.channels` on the routes. Disconnect is a single
 * delete, so it stays inline (there is no webhook to deregister — Slack keeps
 * posting to the dead URL and gets silent 200s until the workspace removes it
 * from their app).
 */
class AgentSlackChannelController
{
    use ExplainsChannelConnectFailures;

    public function connect(ConnectSlackRequest $request, Agent $agent, ConnectSlackAppAction $connect): RedirectResponse
    {
        try {
            $connect->execute($agent, $request->toData());
        } catch (ChannelConnectException $e) {
            return back()->withErrors(['bot_token' => match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => self::publicAgentsOnly('Slack'),
                default => __('Slack rejected that token. Double-check it under OAuth & Permissions in your Slack app and try again.'),
            }]);
        }

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->slackApp?->delete();

        return back();
    }
}
