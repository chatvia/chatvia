<?php

namespace Domain\Channel\Teams\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Teams\Data\ConnectTeamsData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) an Azure bot to an agent: validates the Microsoft
 * App ID + client secret with Entra (by requesting a Bot Framework token —
 * the Teams analog of Slack's auth.test), and stores the secret (encrypted)
 * together with a fresh webhook secret. Throws {@see ChannelConnectException}
 * when the agent is protected or Microsoft rejects the credentials.
 *
 * Like Slack there is no API to register the webhook — the workspace pastes
 * the messaging endpoint (which embeds `webhook_secret`) into their Azure
 * bot's Configuration page and adds the Microsoft Teams channel there.
 */
class ConnectTeamsBotAction
{
    public function __construct(
        private readonly TeamsClient $client,
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, ConnectTeamsData $data): AgentTeamsBot
    {
        $this->guard->ensureAgentIsPublic($agent);

        if (! $this->client->validateCredentials($data->appId, $data->appSecret, $data->tenantId)) {
            throw ChannelConnectException::rejected();
        }

        return AgentTeamsBot::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'app_id' => $data->appId,
                'app_secret' => $data->appSecret,
                'tenant_id' => $data->tenantId,
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );
    }
}
