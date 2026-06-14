<?php

namespace App\Http\Controllers\Agent\Channel;

use App\Http\Requests\Agent\Channel\ConnectTelegramRequest;
use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Actions\ConnectTelegramBotAction;
use Domain\Channel\Telegram\Services\TelegramClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connect / disconnect an agent's Telegram bot. Both gated by `agent.setup`.
 * Connect validates + registers via {@see ConnectTelegramBotAction}; disconnect
 * is a single delete (+ best-effort webhook removal), so it stays inline.
 */
class AgentTelegramChannelController
{
    public function connect(ConnectTelegramRequest $request, Agent $agent, ConnectTelegramBotAction $action): RedirectResponse
    {
        // Telegram (and messaging channels generally) is for public agents only —
        // a Telegram sender is anonymous and can't satisfy a protected agent's
        // access model. Refuse to connect a protected agent.
        if (! $agent->is_public) {
            return back()->withErrors([
                'bot_token' => 'Telegram is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $bot = $action->execute($agent, $request->botToken());

        if ($bot === null) {
            return back()->withErrors([
                'bot_token' => 'Telegram rejected that token. Double-check it from @BotFather and try again.',
            ]);
        }

        return back();
    }

    public function disconnect(Request $request, Agent $agent, TelegramClient $client): RedirectResponse
    {
        $bot = $agent->telegramBot;

        if ($bot !== null) {
            $client->deleteWebhook($bot->bot_token);
            $bot->delete();
        }

        return back();
    }
}
