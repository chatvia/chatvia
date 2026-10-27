<?php

namespace Domain\Channel\Slack\Services;

use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back a Slack channel,
 * creating them on first contact and reusing them thereafter — so a Slack
 * channel (or DM) keeps one ongoing conversation (and its history) across
 * messages. The channel name is looked up once at creation (best-effort) to
 * give the chat-log a readable title.
 *
 * @extends ChannelConversationResolver<SlackChannel>
 */
class SlackChannelResolver extends ChannelConversationResolver
{
    public function __construct(
        StartConversationAction $startConversation,
        private readonly SlackClient $client,
    ) {
        parent::__construct($startConversation);
    }

    public function resolve(AgentSlackApp $app, string $channelId): SlackChannel
    {
        return $this->resolveMapping(
            lookup: SlackChannel::query()
                ->where('agent_slack_app_id', $app->id)
                ->where('channel_id', $channelId),
            agent: $app->agent,
            identity: [
                'agent_slack_app_id' => $app->id,
                'channel_id' => $channelId,
            ],
            source: ConversationSource::Slack,
            context: function () use ($app, $channelId): array {
                $info = $this->client->channelInfo($app->bot_token, $channelId);
                $name = $info['name'] ?? null;

                return [
                    'name' => $name !== null ? "#{$name}" : null,
                    'title' => match (true) {
                        $name !== null => "Slack · #{$name}",
                        (bool) ($info['is_im'] ?? false) => 'Slack · DM',
                        default => 'Slack',
                    },
                    'attributes' => ['channel_name' => $name],
                ];
            },
        );
    }

    /**
     * @return SlackChannel
     */
    protected function createMapping(array $attributes): Model
    {
        return SlackChannel::query()->create($attributes);
    }
}
