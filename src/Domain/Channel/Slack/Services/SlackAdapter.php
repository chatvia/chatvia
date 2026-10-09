<?php

namespace Domain\Channel\Slack\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\StreamsReplies;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The Slack side of a channel turn: one Slack app talking in one channel or
 * DM, in the thread the mention came from (if any). Slack messages can be
 * edited, so the reply streams into a placeholder; `chat.update` is
 * rate-limited to ~50/min, hence the 1.5s edit throttle.
 */
final readonly class SlackAdapter implements StreamsReplies
{
    public function __construct(
        private SlackClient $client,
        private SlackChannelResolver $resolver,
        private AgentSlackApp $app,
        private string $channelId,
        private ?string $threadTs = null,
    ) {}

    /**
     * An `app_mention` arrives as `<@U123ABC> question…` — drop the bot's own
     * mention(s) so the model sees just the question.
     */
    public function withoutBotMention(string $text): string
    {
        if ($this->app->bot_user_id !== null) {
            $text = str_replace("<@{$this->app->bot_user_id}>", '', $text);
        }

        return trim($text);
    }

    public function agent(): Agent
    {
        return $this->app->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::Slack;
    }

    public function displayName(): string
    {
        return 'Slack';
    }

    public function resolveConversation(): SlackChannel
    {
        return $this->resolver->resolve($this->app, $this->channelId);
    }

    public function sendMessage(string $markdown): void
    {
        $this->postMessage($markdown);
    }

    public function postMessage(string $markdown): ?string
    {
        return $this->client->postMessage($this->app->bot_token, $this->channelId, $markdown, $this->threadTs);
    }

    public function updateMessage(string $messageId, string $markdown): bool
    {
        return $this->client->updateMessage($this->app->bot_token, $this->channelId, $messageId, $markdown);
    }

    public function streamThrottleSeconds(): float
    {
        return 1.5;
    }

    public function requestIdPrefix(): string
    {
        return 'slk_';
    }

    public function logContext(): array
    {
        return ['channel_id' => $this->channelId];
    }
}
