<?php

namespace Domain\Channel\Instagram\Actions;

use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Instagram\Services\InstagramReplyRunner;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;

/**
 * Handles one inbound Instagram Direct text message end to end: resolve the
 * thread's Session + Conversation, run the agent through the real pipeline to
 * get a reply, and send it back. Invoked off the webhook via
 * {@see HandleInstagramMessageJob}.
 *
 * Messaging channels are for PUBLIC agents only: an Instagram sender is
 * anonymous to Chatvia and can't satisfy a protected agent's access model.
 * Connecting is blocked for protected agents at the controller; this is the
 * runtime backstop for an agent flipped to protected after it was already
 * connected.
 */
class HandleInstagramMessageAction
{
    private const UNAVAILABLE = 'This assistant isn’t available on Instagram right now.';

    public function __construct(
        private readonly InstagramThreadResolver $resolver,
        private readonly InstagramReplyRunner $runner,
        private readonly InstagramClient $client,
    ) {}

    public function execute(AgentInstagramAccount $account, string $igsid, string $text): void
    {
        // Protected agents are never answered on Instagram (defence in depth).
        if (! $account->agent->is_public) {
            $this->client->sendMessage($account->access_token, $igsid, self::UNAVAILABLE);

            return;
        }

        $thread = $this->resolver->resolve($account, $igsid);

        // Instant feedback: "typing…" the moment the message lands. The runner
        // keeps it alive while the agent (RAG + LLM) generates the reply.
        $this->client->sendTypingAction($account->access_token, $igsid);

        $reply = $this->runner->reply($thread, $text);

        $this->client->sendMessage($account->access_token, $igsid, $reply);

        $thread->forceFill(['last_message_at' => now()])->save();
    }
}
