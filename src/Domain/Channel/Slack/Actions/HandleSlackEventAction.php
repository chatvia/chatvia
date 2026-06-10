<?php

namespace Domain\Channel\Slack\Actions;

use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Channel\Slack\Services\SlackClient;
use Domain\Channel\Slack\Services\SlackReplyRunner;

/**
 * Handles one inbound Slack message end to end: resolve the channel's
 * Session + Conversation, post a placeholder reply immediately, then stream
 * the agent's answer into it by editing the message in place as the pipeline
 * generates. Invoked off the webhook via {@see HandleSlackEventJob}.
 *
 * Messaging channels are for PUBLIC agents only: a Slack sender is anonymous
 * to Chatvia and can't satisfy a protected agent's access model. Connecting is
 * blocked for protected agents at the controller; this is the runtime backstop
 * for an agent flipped to protected after it was already connected.
 */
class HandleSlackEventAction
{
    private const UNAVAILABLE = 'This assistant isn’t available on Slack right now.';

    public function __construct(
        private readonly SlackChannelResolver $resolver,
        private readonly SlackReplyRunner $runner,
        private readonly SlackClient $client,
    ) {}

    public function execute(AgentSlackApp $app, string $channelId, string $text, ?string $threadTs = null): void
    {
        $text = $this->stripMention($app, $text);

        if ($text === '') {
            return;
        }

        // Protected agents are never answered on Slack (defence in depth).
        if (! $app->agent->is_public) {
            $this->client->postMessage($app->bot_token, $channelId, self::UNAVAILABLE, $threadTs);

            return;
        }

        $channel = $this->resolver->resolve($app, $channelId);

        // Instant feedback: a placeholder message the moment the mention
        // lands. Its `ts` is the handle the reply then streams into.
        $ts = $this->client->postMessage($app->bot_token, $channelId, '_Thinking…_', $threadTs);

        // Posting failed (kicked from the channel, missing scope, outage) —
        // bail before the pipeline runs so the turn isn't billed for a reply
        // nobody can see.
        if ($ts === null) {
            return;
        }

        $reply = $this->runner->reply(
            $channel,
            $text,
            fn (string $partial) => $this->client->updateMessage($app->bot_token, $channelId, $ts, $partial),
        );

        $this->client->updateMessage($app->bot_token, $channelId, $ts, $reply);

        $channel->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * An `app_mention` arrives as `<@U123ABC> question…` — drop the bot's own
     * mention(s) so the model sees just the question.
     */
    private function stripMention(AgentSlackApp $app, string $text): string
    {
        if ($app->bot_user_id !== null) {
            $text = str_replace("<@{$app->bot_user_id}>", '', $text);
        }

        return trim($text);
    }
}
