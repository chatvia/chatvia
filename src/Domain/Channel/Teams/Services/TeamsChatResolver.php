<?php

namespace Domain\Channel\Teams\Services;

use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back a Teams conversation,
 * creating them on first contact and reusing them thereafter — so a personal
 * chat, group chat, or channel thread keeps one ongoing conversation (and its
 * history) across messages. The sender's display name (personal chats) titles
 * the chat-log entry. The regional `serviceUrl` arrives on every activity and
 * is refreshed here, since Microsoft may move a conversation between hosts.
 *
 * @extends ChannelConversationResolver<TeamsChat>
 */
class TeamsChatResolver extends ChannelConversationResolver
{
    public function resolve(
        AgentTeamsBot $bot,
        string $conversationId,
        string $serviceUrl,
        ?string $conversationType,
        ?string $peerName,
    ): TeamsChat {
        $title = match (true) {
            $conversationType === 'personal' && $peerName !== null => "Teams · {$peerName}",
            $conversationType === 'personal' => 'Teams · DM',
            $conversationType === 'groupChat' => 'Teams · Group chat',
            $conversationType === 'channel' => 'Teams · Channel',
            default => 'Teams',
        };

        return $this->resolveMapping(
            lookup: TeamsChat::query()
                ->where('agent_teams_bot_id', $bot->id)
                ->where('conversation_id', $conversationId),
            agent: $bot->agent,
            identity: [
                'agent_teams_bot_id' => $bot->id,
                'conversation_id' => $conversationId,
                'service_url' => $serviceUrl,
                'conversation_type' => $conversationType,
                'peer_name' => $peerName,
            ],
            source: ConversationSource::MsTeams,
            context: fn (): array => [
                'name' => $conversationType === 'personal' ? $peerName : null,
                'title' => $title,
            ],
            onReuse: function (TeamsChat $existing) use ($serviceUrl): void {
                if ($existing->service_url !== $serviceUrl) {
                    $existing->update(['service_url' => $serviceUrl]);
                }
            },
        );
    }

    /**
     * @return TeamsChat
     */
    protected function createMapping(array $attributes): Model
    {
        return TeamsChat::create($attributes);
    }
}
