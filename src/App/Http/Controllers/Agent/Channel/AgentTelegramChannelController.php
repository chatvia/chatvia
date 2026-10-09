<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Controllers\Agent\Channel\Concerns\ExplainsChannelConnectFailures;
use App\Http\Requests\Agent\Channel\ConnectTelegramRequest;
use App\Http\Requests\Agent\Channel\UpdateTelegramCommandsRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Telegram\Actions\ConnectTelegramBotAction;
use Domain\Channel\Telegram\Actions\DisconnectTelegramBotAction;
use Domain\Channel\Telegram\Actions\UpdateTelegramCommandsAction;
use Illuminate\Http\RedirectResponse;

/**
 * Connect / disconnect an agent's Telegram bot, and manage its custom commands.
 * All gated by `agent.permission:agent.channels` on the routes. Messaging
 * channels are public-agents only ({@see ConnectTelegramBotAction}).
 */
class AgentTelegramChannelController
{
    use ExplainsChannelConnectFailures;

    public function connect(ConnectTelegramRequest $request, Agent $agent, ConnectTelegramBotAction $connect): RedirectResponse
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

    public function disconnect(Agent $agent, DisconnectTelegramBotAction $disconnect): RedirectResponse
    {
        if ($agent->telegramBot !== null) {
            $disconnect->execute($agent->telegramBot);
        }

        return back();
    }

    public function updateCommands(UpdateTelegramCommandsRequest $request, Agent $agent, UpdateTelegramCommandsAction $update): RedirectResponse
    {
        if ($agent->telegramBot === null) {
            return back()->withErrors(['commands' => __('Connect a Telegram bot before adding commands.')]);
        }

        $update->execute($agent->telegramBot, $request->commands());

        return back();
    }
}
