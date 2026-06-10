<?php

namespace Domain\Channel\Messenger\Services;

use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Services\ChannelReplyRunner;

/**
 * Runs one Messenger turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}) and collects the **full reply as a string** —
 * Messenger messages can't be edited into a stream, so the only
 * mid-generation feedback is keeping the "typing…" indicator alive while the
 * agent works.
 */
class MessengerReplyRunner
{
    /** Re-send the typing indicator at most this often (Messenger clears it after ~20s). */
    private const TYPING_REFRESH_SECONDS = 15.0;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
        private readonly MessengerClient $client,
    ) {}

    public function reply(MessengerThread $thread, string $message): string
    {
        $token = $thread->page->page_access_token;
        $lastTyping = microtime(true);

        return $this->runner->reply(
            agent: $thread->page->agent,
            sessionExternalId: $thread->session_external_id,
            conversationExternalId: $thread->conversation_external_id,
            message: $message,
            source: 'messenger',
            requestIdPrefix: 'msgr_',
            onEvent: function () use ($token, $thread, &$lastTyping): void {
                if (microtime(true) - $lastTyping >= self::TYPING_REFRESH_SECONDS) {
                    $this->client->sendTypingAction($token, $thread->psid);
                    $lastTyping = microtime(true);
                }
            },
            logContext: ['psid' => $thread->psid],
        );
    }
}
