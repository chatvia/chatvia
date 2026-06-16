<?php

namespace Domain\Channel\Instagram\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;

/**
 * Stores (or replaces) the Instagram professional account an agent answers on,
 * after the workspace granted it through Facebook Login for Business. The page
 * access token comes straight from Meta's /me/accounts (already valid), so we
 * persist it (encrypted) and best-effort subscribe the linked Page to its
 * `messages` webhook field — without that Meta never delivers the IG DMs to
 * Chatvia's app-level Callback URL. One account per agent (`agent_id` is
 * unique); inbound events are routed back to this row by `ig_id`.
 */
class ConnectInstagramAccountAction
{
    public function __construct(
        private readonly InstagramClient $client,
    ) {}

    public function execute(
        Agent $agent,
        string $igId,
        string $igUsername,
        string $pageId,
        string $pageAccessToken,
    ): AgentInstagramAccount {
        $account = AgentInstagramAccount::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'page_access_token' => $pageAccessToken,
                'page_id' => $pageId,
                'ig_id' => $igId,
                'username' => $igUsername,
                'enabled' => true,
            ],
        );

        $this->client->subscribeAccount($pageAccessToken, $pageId);

        return $account;
    }
}
