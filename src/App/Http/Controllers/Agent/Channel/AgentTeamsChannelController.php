<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\ExplainsChannelConnectFailures;
use App\Http\Requests\Agent\Channel\ConnectTeamsRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Teams\Actions\ConnectTeamsBotAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connect / disconnect an agent's Microsoft Teams bot. Both gated by
 * `agent.permission:agent.channels` on the routes. Disconnect is a single
 * delete, so it stays inline (there is no webhook to deregister — the
 * connector keeps posting to the dead URL and gets silent 200s until the
 * workspace removes the endpoint from their Azure bot).
 */
class AgentTeamsChannelController
{
    use ExplainsChannelConnectFailures;

    public function connect(ConnectTeamsRequest $request, Agent $agent, ConnectTeamsBotAction $connect): RedirectResponse
    {
        try {
            $connect->execute($agent, $request->toData());
        } catch (ChannelConnectException $e) {
            return back()->withErrors(match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => [
                    'app_id' => self::publicAgentsOnly('Microsoft Teams'),
                ],
                default => [
                    'app_secret' => __('Microsoft rejected those credentials. Double-check the App ID, client secret, and tenant ID in your Azure bot and try again.'),
                ],
            });
        }

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->teamsBot?->delete();

        return back();
    }
}
