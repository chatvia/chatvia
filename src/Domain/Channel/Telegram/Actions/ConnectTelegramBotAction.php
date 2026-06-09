<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a Telegram bot to an agent: validates the token with
 * Telegram, stores it (encrypted) with a fresh webhook secret, and registers our
 * webhook with Telegram. Returns null when the token is invalid/unreachable so
 * the controller can surface a field error.
 *
 * Webhook registration is best-effort — on a non-public host (local dev) Telegram
 * can't reach the URL, so the bot is still saved and the webhook can be set once
 * the app is reachable.
 */
class ConnectTelegramBotAction
{
    public function __construct(
        private readonly TelegramClient $client,
    ) {}

    public function execute(Agent $agent, string $token): ?AgentTelegramBot
    {
        $me = $this->client->getMe($token);

        if ($me === null) {
            return null;
        }

        $secret = Str::random(48);

        $bot = AgentTelegramBot::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'bot_token' => $token,
                'bot_username' => $me['username'],
                'webhook_secret' => $secret,
                'enabled' => true,
            ],
        );

        $this->client->setWebhook($token, route('telegram.webhook', ['secret' => $secret]), $secret);

        return $bot;
    }
}
