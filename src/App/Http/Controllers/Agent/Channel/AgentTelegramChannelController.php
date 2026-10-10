<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\ExplainsChannelConnectFailures;
use App\Http\Requests\Agent\Channel\ConnectTelegramRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Telegram\Actions\ConnectTelegramBotAction;
use Domain\Channel\Telegram\Actions\DisconnectTelegramBotAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connect / disconnect an agent's Telegram bot; its custom commands live in
 * {@see UpdateAgentTelegramCommandsController}. Both gated by
 * `agent.permission:agent.channels` on the routes. Messaging channels are
 * public-agents only ({@see ConnectTelegramBotAction}).
 */
class AgentTelegramChannelController
{
    use ExplainsChannelConnectFailures;

    public function store(ConnectTelegramRequest $request, Agent $agent, ConnectTelegramBotAction $connect): RedirectResponse
    {
        try {
            $connect->execute($agent, $request->toData());
        } catch (ChannelConnectException $e) {
            return back()->withErrors(['bot_token' => match ($e->failure) {
                ChannelConnectFailure::ProtectedAgent => self::publicAgentsOnly('Telegram'),
                default => __('Telegram rejected that token. Double-check it from @BotFather and try again.'),
            }]);
        }

        return back();
    }

    public function destroy(Agent $agent, DisconnectTelegramBotAction $disconnect): RedirectResponse
    {
        if ($agent->telegramBot !== null) {
            $disconnect->execute($agent->telegramBot);
        }

        return back();
    }
}
