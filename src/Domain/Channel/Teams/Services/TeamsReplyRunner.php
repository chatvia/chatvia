<?php

namespace Domain\Channel\Teams\Services;

use Closure;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Conversation\Services\ChatContext;

/**
 * Runs one Teams turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}). Like Slack, Teams messages can be edited in
 * place, so the reply genuinely streams: `$onPartial` fires (throttled — the
 * connector rate-limits a bot harder than Slack's chat.update tier) with the
 * text so far, and the caller rewrites the placeholder activity with it.
 */
class TeamsReplyRunner
{
    /** Push a partial update at most this often (Teams throttles per-conversation bot traffic). */
    private const PARTIAL_THROTTLE_SECONDS = 2.0;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
    ) {}

    /**
     * @param  Closure(string): void|null  $onPartial  invoked with the accumulated reply-so-far
     */
    public function reply(TeamsChat $chat, string $message, ?Closure $onPartial = null): string
    {
        $lastPush = microtime(true);
        $lastPushed = '';

        return $this->runner->reply(
            agent: $chat->bot->agent,
            sessionExternalId: $chat->session_external_id,
            conversationExternalId: $chat->conversation_external_id,
            message: $message,
            source: 'msteams',
            requestIdPrefix: 'mst_',
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
            logContext: ['conversation_id' => $chat->conversation_id],
        );
    }
}
