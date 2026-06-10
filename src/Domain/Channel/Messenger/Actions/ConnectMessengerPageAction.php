<?php

namespace Domain\Channel\Messenger\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a Facebook Page to an agent: validates the page
 * access token with the Graph API, stores it (encrypted) together with the
 * Meta app secret and a fresh webhook secret, and subscribes the Meta app to
 * the page's `messages` field (best-effort) so the webhook starts firing.
 * Returns null when the token is invalid/unreachable so the controller can
 * surface a field error.
 *
 * The app-level webhook itself can't be registered via API — the workspace
 * pastes the Callback URL + verify token (both embed `webhook_secret`) into
 * their Meta app's Messenger → Webhooks settings; Meta then verifies it with
 * a GET handshake our webhook answers.
 */
class ConnectMessengerPageAction
{
    public function __construct(
        private readonly MessengerClient $client,
    ) {}

    public function execute(Agent $agent, string $pageAccessToken, string $appSecret): ?AgentMessengerPage
    {
        $info = $this->client->pageInfo($pageAccessToken);

        if ($info === null) {
            return null;
        }

        $page = AgentMessengerPage::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'page_access_token' => $pageAccessToken,
                'app_secret' => $appSecret,
                'page_id' => $info['id'],
                'page_name' => $info['name'],
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );

        $this->client->subscribePage($pageAccessToken, $info['id']);

        return $page;
    }
}
