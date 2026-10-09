<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Telegram\Data\ConnectTelegramData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramClient;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a Telegram bot to an agent: validates the token with
 * Telegram, stores it (encrypted) with a fresh webhook secret, and registers our
 * webhook with Telegram. Throws {@see ChannelConnectException} when the agent
 * is protected or Telegram rejects the token.
 *
 * Webhook registration is best-effort — on a non-public host (local dev) Telegram
 * can't reach the URL, so the bot is still saved and the webhook can be set once
 * the app is reachable.
 */
class ConnectTelegramBotAction
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly TelegramCommandRouter $commands,
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, ConnectTelegramData $data): AgentTelegramBot
    {
        $this->guard->ensureAgentIsPublic($agent);

        $me = $this->client->getMe($data->botToken);

        if ($me === null) {
            throw ChannelConnectException::rejected();
        }

        $secret = Str::random(48);

        $bot = AgentTelegramBot::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'bot_token' => $data->botToken,
                'bot_username' => $me['username'],
                'webhook_secret' => $secret,
                'enabled' => true,
            ],
        );

        $this->client->setWebhook($data->botToken, route('telegram.webhook', ['secret' => $secret]), $secret);

        // Surface the command menu (built-ins + any commands kept across a
        // reconnect) in Telegram's "/" autocomplete.
        $this->commands->sync($bot);

        return $bot;
    }
}
