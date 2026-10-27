<?php

namespace Domain\Channel\Slack\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Slack\Data\ConnectSlackData;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a Slack app to an agent: validates the bot token
 * with Slack (auth.test), and stores it (encrypted) together with the signing
 * secret and a fresh webhook secret. Throws {@see ChannelConnectException} when
 * the agent is protected or Slack rejects the token.
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
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, ConnectSlackData $data): AgentSlackApp
    {
        $this->guard->ensureAgentIsPublic($agent);

        $identity = $this->client->authTest($data->botToken);

        if ($identity === null) {
            throw ChannelConnectException::rejected();
        }

        return AgentSlackApp::query()->updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'bot_token' => $data->botToken,
                'signing_secret' => $data->signingSecret,
                'bot_user_id' => $identity['user_id'],
                'team_name' => $identity['team'],
                'webhook_secret' => Str::random(48),
                'is_enabled' => true,
            ],
        );
    }
}
