<?php

namespace Domain\Channel\Messenger\Actions;

use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Messenger\Services\MessengerReplyRunner;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Services\ChannelAccessGate;

/**
 * Handles one inbound Messenger text message end to end: resolve the thread's
 * Session + Conversation, run the agent through the real pipeline to get a
 * reply, and send it back. Invoked off the webhook via
 * {@see HandleMessengerMessageJob}.
 *
 * Messaging channels are for PUBLIC agents only: a Messenger sender is
 * anonymous to Chatvia and can't satisfy a protected agent's access model.
 * Connecting is blocked for protected agents at the controller; this is the
 * runtime backstop for an agent flipped to protected after it was already
 * connected.
 */
class HandleMessengerMessageAction
{
    private const UNAVAILABLE = 'This assistant isn’t available on Messenger right now.';

    public function __construct(
        private readonly MessengerThreadResolver $resolver,
        private readonly MessengerReplyRunner $runner,
        private readonly MessengerClient $client,
        private readonly ChannelAccessGate $gate,
    ) {}

    public function execute(AgentMessengerPage $page, string $psid, string $text): void
    {
        // Protected agents are never answered on Messenger (defence in depth).
        if (! $this->gate->allows($page->agent, fn () => $this->client->sendMessage($page->page_access_token, $psid, self::UNAVAILABLE))) {
            return;
        }

        $thread = $this->resolver->resolve($page, $psid);

        // Instant feedback: "typing…" the moment the message lands. The runner
        // keeps it alive while the agent (RAG + LLM) generates the reply.
        $this->client->sendTypingAction($page->page_access_token, $psid);

        $reply = $this->runner->reply($thread, $text);

        $this->client->sendMessage($page->page_access_token, $psid, $reply);

        $thread->forceFill(['last_message_at' => now()])->save();
    }
}
