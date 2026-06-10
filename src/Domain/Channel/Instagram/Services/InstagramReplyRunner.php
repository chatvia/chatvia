<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Services\ChannelReplyRunner;

/**
 * Runs one Instagram Direct turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}) and collects the **full reply as a string** —
 * Instagram messages can't be edited into a stream, so the only
 * mid-generation feedback is keeping the "typing…" indicator alive while the
 * agent works.
 */
class InstagramReplyRunner
{
    /** Re-send the typing indicator at most this often (Instagram clears it after ~20s). */
    private const TYPING_REFRESH_SECONDS = 15.0;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
        private readonly InstagramClient $client,
    ) {}

    public function reply(InstagramThread $thread, string $message): string
    {
        $token = $thread->account->access_token;
        $lastTyping = microtime(true);

        return $this->runner->reply(
            agent: $thread->account->agent,
            sessionExternalId: $thread->session_external_id,
            conversationExternalId: $thread->conversation_external_id,
            message: $message,
            source: 'instagram',
            requestIdPrefix: 'ig_',
            onEvent: function () use ($token, $thread, &$lastTyping): void {
                if (microtime(true) - $lastTyping >= self::TYPING_REFRESH_SECONDS) {
                    $this->client->sendTypingAction($token, $thread->igsid);
                    $lastTyping = microtime(true);
                }
            },
            logContext: ['igsid' => $thread->igsid],
        );
    }
}
