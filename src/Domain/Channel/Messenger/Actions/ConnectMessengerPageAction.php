<?php

namespace Domain\Channel\Messenger\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;

/**
 * Stores (or replaces) the Facebook Page an agent answers on, after the
 * workspace granted it through Facebook Login for Business. The page access
 * token comes straight from Meta's `/me/accounts` (already valid), so we just
 * persist it (encrypted) and best-effort subscribe the Page to its `messages`
 * webhook field — without that Meta never delivers events to Chatvia's
 * app-level Callback URL. One Page per agent (`agent_id` is unique); inbound
 * events are routed back to this row by `page_id`.
 */
class ConnectMessengerPageAction
{
    public function __construct(
        private readonly MessengerClient $client,
    ) {}

    public function execute(Agent $agent, string $pageId, string $pageName, string $pageAccessToken): AgentMessengerPage
    {
        $page = AgentMessengerPage::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'page_access_token' => $pageAccessToken,
                'page_id' => $pageId,
                'page_name' => $pageName,
                'enabled' => true,
            ],
        );

        $this->client->subscribePage($pageAccessToken, $pageId);

        return $page;
    }
}
