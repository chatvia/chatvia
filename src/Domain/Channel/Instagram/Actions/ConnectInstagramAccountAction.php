<?php

namespace Domain\Channel\Instagram\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) an Instagram professional account to an agent:
 * validates the access token with the Graph API, stores it (encrypted)
 * together with the Instagram app secret and a fresh webhook secret, and
 * subscribes the app to the account's `messages` field (best-effort) so the
 * webhook starts firing. Returns null when the token is invalid/unreachable
 * so the controller can surface a field error.
 *
 * The app-level webhook itself can't be registered via API — the workspace
 * pastes the Callback URL + verify token (both embed `webhook_secret`) into
 * their Instagram app's webhook settings; Meta then verifies it with a GET
 * handshake our webhook answers.
 */
class ConnectInstagramAccountAction
{
    public function __construct(
        private readonly InstagramClient $client,
    ) {}

    public function execute(Agent $agent, string $accessToken, string $appSecret): ?AgentInstagramAccount
    {
        $info = $this->client->accountInfo($accessToken);

        if ($info === null) {
            return null;
        }

        $account = AgentInstagramAccount::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'access_token' => $accessToken,
                'app_secret' => $appSecret,
                'ig_id' => $info['id'],
                'username' => $info['username'],
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );

        $this->client->subscribeAccount($accessToken);

        return $account;
    }
}
