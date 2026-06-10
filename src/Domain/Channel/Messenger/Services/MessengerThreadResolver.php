<?php

namespace Domain\Channel\Messenger\Services;

use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back a Messenger thread,
 * creating them on first contact and reusing them thereafter — so a Messenger
 * user keeps one ongoing conversation (and its history) across messages. The
 * user's first name is looked up once at creation (best-effort) to give the
 * chat-log a readable title.
 */
class MessengerThreadResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly MessengerClient $client,
    ) {}

    public function resolve(AgentMessengerPage $page, string $psid): MessengerThread
    {
        $existing = MessengerThread::query()
            ->where('agent_messenger_page_id', $page->id)
            ->where('psid', $psid)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $agent = $page->agent;
        $profile = $this->client->userProfile($page->page_access_token, $psid);
        $firstName = $profile['first_name'] ?? null;

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $firstName,
        ]);

        $conversationId = $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $firstName !== null ? "Messenger · {$firstName}" : 'Messenger',
                source: ConversationSource::Messenger,
            );

        return MessengerThread::create([
            'agent_messenger_page_id' => $page->id,
            'psid' => $psid,
            'session_external_id' => $session->external_id,
            'conversation_external_id' => $conversationId,
            'first_name' => $firstName,
            'last_message_at' => now(),
        ]);
    }
}
