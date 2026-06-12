<?php

namespace Domain\Channel\Teams\Services;

use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back a Teams conversation,
 * creating them on first contact and reusing them thereafter — so a personal
 * chat, group chat, or channel thread keeps one ongoing conversation (and its
 * history) across messages. The sender's display name (personal chats) titles
 * the chat-log entry. The regional `serviceUrl` arrives on every activity and
 * is refreshed here, since Microsoft may move a conversation between hosts.
 */
class TeamsChatResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function resolve(
        AgentTeamsBot $bot,
        string $conversationId,
        string $serviceUrl,
        ?string $conversationType,
        ?string $peerName,
    ): TeamsChat {
        $existing = TeamsChat::query()
            ->where('agent_teams_bot_id', $bot->id)
            ->where('conversation_id', $conversationId)
            ->first();

        if ($existing !== null) {
            // conversation() excludes soft-deleted rows, so a retention-pruned
            // conversation reads as missing here.
            if ($existing->conversation()->exists()) {
                if ($existing->service_url !== $serviceUrl) {
                    $existing->update(['service_url' => $serviceUrl]);
                }

                return $existing;
            }

            // Conversation was retention-pruned — drop the stale mapping and
            // mint a fresh session + conversation below.
            $existing->delete();
        }

        $agent = $bot->agent;

        $title = match (true) {
            $conversationType === 'personal' && $peerName !== null => "Teams · {$peerName}",
            $conversationType === 'personal' => 'Teams · DM',
            $conversationType === 'groupChat' => 'Teams · Group chat',
            $conversationType === 'channel' => 'Teams · Channel',
            default => 'Teams',
        };

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $conversationType === 'personal' ? $peerName : null,
        ]);

        $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $title,
                source: ConversationSource::MsTeams,
            );

        // The freshly-created session has exactly one conversation — the one
        // createConversation() just made for it.
        $conversation = $session->conversations()->latest('id')->firstOrFail();

        return TeamsChat::create([
            'agent_teams_bot_id' => $bot->id,
            'conversation_id' => $conversationId,
            'service_url' => $serviceUrl,
            'conversation_type' => $conversationType,
            'chat_session_id' => $session->id,
            'chat_conversation_id' => $conversation->id,
            'peer_name' => $peerName,
            'last_message_at' => now(),
        ]);
    }
}
