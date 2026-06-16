<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back an Instagram Direct
 * thread, creating them on first contact and reusing them thereafter — so an
 * Instagram user keeps one ongoing conversation (and its history) across
 * messages. The user's name (or @username) is looked up once at creation
 * (best-effort) to give the chat-log a readable title.
 *
 * @extends ChannelConversationResolver<InstagramThread>
 */
class InstagramThreadResolver extends ChannelConversationResolver
{
    public function __construct(
        ChatMemory $memory,
        private readonly InstagramClient $client,
    ) {
        parent::__construct($memory);
    }

    public function resolve(AgentInstagramAccount $account, string $igsid): InstagramThread
    {
        return $this->resolveMapping(
            lookup: InstagramThread::query()
                ->where('agent_instagram_account_id', $account->id)
                ->where('igsid', $igsid),
            agent: $account->agent,
            identity: [
                'agent_instagram_account_id' => $account->id,
                'igsid' => $igsid,
            ],
            source: ConversationSource::Instagram,
            context: function () use ($account, $igsid): array {
                $profile = $this->client->userProfile($account->page_access_token, $igsid);
                $username = $profile['username'] ?? null;
                $name = $profile['name'] ?? ($username !== null ? "@{$username}" : null);

                return [
                    'name' => $name,
                    'title' => $name !== null ? "Instagram · {$name}" : 'Instagram',
                    'attributes' => ['name' => $name],
                ];
            },
        );
    }

    /**
     * @return InstagramThread
     */
    protected function createMapping(array $attributes): Model
    {
        return InstagramThread::create($attributes);
    }
}
