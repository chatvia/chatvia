<?php

namespace Domain\Channel\Slack\Services;

use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back a Slack channel,
 * creating them on first contact and reusing them thereafter — so a Slack
 * channel (or DM) keeps one ongoing conversation (and its history) across
 * messages. The channel name is looked up once at creation (best-effort) to
 * give the chat-log a readable title.
 */
class SlackChannelResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly SlackClient $client,
    ) {}

    public function resolve(AgentSlackApp $app, string $channelId): SlackChannel
    {
        $existing = SlackChannel::query()
            ->where('agent_slack_app_id', $app->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $agent = $app->agent;
        $info = $this->client->channelInfo($app->bot_token, $channelId);
        $name = $info['name'] ?? null;

        $title = match (true) {
            $name !== null => "Slack · #{$name}",
            (bool) ($info['is_im'] ?? false) => 'Slack · DM',
            default => 'Slack',
        };

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $name !== null ? "#{$name}" : null,
        ]);

        $conversationId = $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $title,
                source: ConversationSource::Slack,
            );

        return SlackChannel::create([
            'agent_slack_app_id' => $app->id,
            'channel_id' => $channelId,
            'session_external_id' => $session->external_id,
            'conversation_external_id' => $conversationId,
            'channel_name' => $name,
            'last_message_at' => now(),
        ]);
    }
}
