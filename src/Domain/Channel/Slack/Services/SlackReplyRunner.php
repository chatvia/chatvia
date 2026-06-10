<?php

namespace Domain\Channel\Slack\Services;

use Closure;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Conversation\Services\ChatContext;

/**
 * Runs one Slack turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}). Unlike Telegram, Slack messages can be edited
 * in place, so the reply genuinely streams: `$onPartial` fires (throttled —
 * chat.update is rate-limited to ~50/min) with the text so far, and the
 * caller rewrites the placeholder message with it.
 */
class SlackReplyRunner
{
    /** Push a partial update at most this often (Slack's chat.update tier allows ~50/min). */
    private const PARTIAL_THROTTLE_SECONDS = 1.5;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
    ) {}

    /**
     * @param  Closure(string): void|null  $onPartial  invoked with the accumulated reply-so-far
     */
    public function reply(SlackChannel $channel, string $message, ?Closure $onPartial = null): string
    {
        $lastPush = microtime(true);
        $lastPushed = '';

        return $this->runner->reply(
            agent: $channel->app->agent,
            sessionExternalId: $channel->session_external_id,
            conversationExternalId: $channel->conversation_external_id,
            message: $message,
            source: 'slack',
            requestIdPrefix: 'slk_',
            onEvent: $onPartial === null ? null : function (ChatContext $context) use ($onPartial, &$lastPush, &$lastPushed): void {
                if (microtime(true) - $lastPush < self::PARTIAL_THROTTLE_SECONDS) {
                    return;
                }

                $partial = trim($context->streamedMessage);

                if ($partial !== '' && $partial !== $lastPushed) {
                    $onPartial($partial);
                    $lastPushed = $partial;
                }

                $lastPush = microtime(true);
            },
            logContext: ['channel_id' => $channel->channel_id],
        );
    }
}
