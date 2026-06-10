<?php

namespace Domain\Channel\Teams\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) an Azure bot to an agent: validates the Microsoft
 * App ID + client secret with Entra (by requesting a Bot Framework token —
 * the Teams analog of Slack's auth.test), and stores the secret (encrypted)
 * together with a fresh webhook secret. Returns null when the credentials are
 * invalid/unreachable so the controller can surface a field error.
 *
 * Like Slack there is no API to register the webhook — the workspace pastes
 * the messaging endpoint (which embeds `webhook_secret`) into their Azure
 * bot's Configuration page and adds the Microsoft Teams channel there.
 */
class ConnectTeamsBotAction
{
    public function __construct(
        private readonly TeamsClient $client,
    ) {}

    public function execute(Agent $agent, string $appId, string $appSecret, ?string $tenantId): ?AgentTeamsBot
    {
        if (! $this->client->validateCredentials($appId, $appSecret, $tenantId)) {
            return null;
        }

        return AgentTeamsBot::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'tenant_id' => $tenantId,
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );
    }
}
