<?php

namespace Domain\Channel\Messenger\Services;

use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back a Messenger thread,
 * creating them on first contact and reusing them thereafter — so a Messenger
 * user keeps one ongoing conversation (and its history) across messages. The
 * user's first name is looked up once at creation (best-effort) to give the
 * chat-log a readable title.
 *
 * @extends ChannelConversationResolver<MessengerThread>
 */
class MessengerThreadResolver extends ChannelConversationResolver
{
    public function __construct(
        StartConversationAction $startConversation,
        private readonly MessengerClient $client,
    ) {
        parent::__construct($startConversation);
    }

    public function resolve(AgentMessengerPage $page, string $psid): MessengerThread
    {
        return $this->resolveMapping(
            lookup: MessengerThread::query()
                ->where('agent_messenger_page_id', $page->id)
                ->where('psid', $psid),
            agent: $page->agent,
            identity: [
                'agent_messenger_page_id' => $page->id,
                'psid' => $psid,
            ],
            source: ConversationSource::Messenger,
            context: function () use ($page, $psid): array {
                $profile = $this->client->userProfile($page->page_access_token, $psid);
                $firstName = $profile['first_name'] ?? null;

                return [
                    'name' => $firstName,
                    'title' => $firstName !== null ? "Messenger · {$firstName}" : 'Messenger',
                    'attributes' => ['first_name' => $firstName],
                ];
            },
        );
    }

    /**
     * @return MessengerThread
     */
    protected function createMapping(array $attributes): Model
    {
        return MessengerThread::query()->create($attributes);
    }
}
