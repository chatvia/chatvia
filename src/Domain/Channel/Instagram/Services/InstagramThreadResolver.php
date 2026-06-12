<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back an Instagram Direct
 * thread, creating them on first contact and reusing them thereafter — so an
 * Instagram user keeps one ongoing conversation (and its history) across
 * messages. The user's name (or @username) is looked up once at creation
 * (best-effort) to give the chat-log a readable title.
 */
class InstagramThreadResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly InstagramClient $client,
    ) {}

    public function resolve(AgentInstagramAccount $account, string $igsid): InstagramThread
    {
        $existing = InstagramThread::query()
            ->where('agent_instagram_account_id', $account->id)
            ->where('igsid', $igsid)
            ->first();

        if ($existing !== null) {
            // conversation() excludes soft-deleted rows, so a retention-pruned
            // conversation reads as missing here.
            if ($existing->conversation()->exists()) {
                return $existing;
            }

            // Conversation was retention-pruned — drop the stale mapping and
            // mint a fresh session + conversation below.
            $existing->delete();
        }

        $agent = $account->agent;
        $profile = $this->client->userProfile($account->access_token, $igsid);
        $username = $profile['username'] ?? null;
        $name = $profile['name'] ?? ($username !== null ? "@{$username}" : null);

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $name,
        ]);

        $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $name !== null ? "Instagram · {$name}" : 'Instagram',
                source: ConversationSource::Instagram,
            );

        // The freshly-created session has exactly one conversation — the one
        // createConversation() just made for it.
        $conversation = $session->conversations()->latest('id')->firstOrFail();

        return InstagramThread::create([
            'agent_instagram_account_id' => $account->id,
            'igsid' => $igsid,
            'chat_session_id' => $session->id,
            'chat_conversation_id' => $conversation->id,
            'name' => $name,
            'last_message_at' => now(),
        ]);
    }
}
