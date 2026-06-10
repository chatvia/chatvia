<?php

namespace Domain\Channel\Slack\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a Slack app to an agent: validates the bot token
 * with Slack (auth.test), and stores it (encrypted) together with the signing
 * secret and a fresh webhook secret. Returns null when the token is
 * invalid/unreachable so the controller can surface a field error.
 *
 * Unlike Telegram there is no API to register the webhook — the workspace
 * pastes the Request URL (which embeds `webhook_secret`) into their Slack
 * app's Event Subscriptions page; Slack then verifies it with a
 * `url_verification` challenge our webhook answers.
 */
class ConnectSlackAppAction
{
    public function __construct(
        private readonly SlackClient $client,
    ) {}

    public function execute(Agent $agent, string $botToken, string $signingSecret): ?AgentSlackApp
    {
        $identity = $this->client->authTest($botToken);

        if ($identity === null) {
            return null;
        }

        return AgentSlackApp::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'bot_token' => $botToken,
                'signing_secret' => $signingSecret,
                'bot_user_id' => $identity['user_id'],
                'team_name' => $identity['team'],
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );
    }
}
