<?php

namespace Domain\Channel\Instagram\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Services\ChannelConnectionGuard;

/**
 * Stores (or replaces) the Instagram professional account an agent answers on,
 * after the workspace granted it through Facebook Login for Business. The page
 * access token comes straight from Meta's /me/accounts (already valid), so we
 * persist it (encrypted) and best-effort subscribe the linked Page to its
 * `messages` webhook field — without that Meta never delivers the IG DMs to
 * Chatvia's app-level Callback URL. One account per agent (`agent_id` is
 * unique) and one agent per account (`ig_id` is unique among enabled rows);
 * inbound events are routed back to the enabled row by `ig_id`. Throws {@see ChannelConnectException}
 * when the agent is protected or the account already powers another agent.
 */
class ConnectInstagramAccountAction
{
    public function __construct(
        private readonly InstagramClient $client,
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, InstagramAccountData $account): AgentInstagramAccount
    {
        $this->guard->ensureAgentIsPublic($agent);
        $this->guard->ensureAccountIsFree($agent, AgentInstagramAccount::query()->where('ig_id', $account->ig_id));

        $connection = $this->guard->store(fn (): AgentInstagramAccount => AgentInstagramAccount::query()->updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'page_access_token' => $account->page_access_token,
                'page_id' => $account->page_id,
                'ig_id' => $account->ig_id,
                'username' => $account->ig_username,
                'is_enabled' => true,
            ],
        ));

        $this->client->subscribeAccount($account->page_access_token, $account->page_id);

        return $connection;
    }
}
