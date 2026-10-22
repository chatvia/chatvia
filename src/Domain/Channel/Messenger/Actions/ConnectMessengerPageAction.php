<?php

namespace Domain\Channel\Messenger\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Services\ChannelConnectionGuard;

/**
 * Stores (or replaces) the Facebook Page an agent answers on, after the
 * workspace granted it through Facebook Login for Business. The page access
 * token comes straight from Meta's `/me/accounts` (already valid), so we just
 * persist it (encrypted) and best-effort subscribe the Page to its `messages`
 * webhook field — without that Meta never delivers events to Chatvia's
 * app-level Callback URL. One Page per agent (`agent_id` is unique) and one
 * agent per Page (`page_id` is unique among enabled rows); inbound events are
 * routed back to the enabled row by `page_id`. Throws
 * {@see ChannelConnectException} when the agent is protected or the Page
 * already powers another agent.
 */
class ConnectMessengerPageAction
{
    public function __construct(
        private readonly MessengerClient $client,
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, MetaPageData $page): AgentMessengerPage
    {
        $this->guard->ensureAgentIsPublic($agent);
        $this->guard->ensureAccountIsFree($agent, AgentMessengerPage::query()->where('page_id', $page->id));

        $connection = $this->guard->store(fn (): AgentMessengerPage => AgentMessengerPage::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'page_access_token' => $page->access_token,
                'page_id' => $page->id,
                'page_name' => $page->name,
                'is_enabled' => true,
            ],
        ));

        $this->client->subscribePage($page->access_token, $page->id);

        return $connection;
    }
}
