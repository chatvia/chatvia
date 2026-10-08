<?php

namespace Domain\Channel\Teams\Actions;

use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Services\ChannelLocale;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Channel\Teams\Services\TeamsClient;
use Domain\Channel\Teams\Services\TeamsReplyRunner;

/**
 * Handles one inbound Teams message activity end to end: strip the bot's
 * own mention, resolve the conversation's Session + Conversation, post a
 * placeholder reply immediately, then stream the agent's answer into it by
 * editing the activity in place as the pipeline generates. Invoked off the
 * webhook via {@see HandleTeamsActivityJob}.
 *
 * Messaging channels are for PUBLIC agents only: a Teams sender is anonymous
 * to Chatvia and can't satisfy a protected agent's access model. Connecting is
 * blocked for protected agents at the controller; this is the runtime backstop
 * for an agent flipped to protected after it was already connected.
 */
class HandleTeamsActivityAction
{
    /** Shown in the channel's own name, so every channel shares one translation. */
    private const CHANNEL_NAME = 'Microsoft Teams';

    public function __construct(
        private readonly TeamsChatResolver $resolver,
        private readonly TeamsReplyRunner $runner,
        private readonly TeamsClient $client,
        private readonly ChannelAccessGate $gate,
    ) {}

    /**
     * @param  array<string, mixed>  $activity  the Bot Framework message activity, as delivered
     */
    public function execute(AgentTeamsBot $bot, array $activity): void
    {
        $text = $this->extractText($activity);

        if ($text === '') {
            return;
        }

        $conversationId = (string) $activity['conversation']['id'];
        $serviceUrl = (string) $activity['serviceUrl'];

        // Protected agents are never answered on Teams (defence in depth).
        if (! $this->gate->allows($bot->agent, fn (): ?string => $this->client->postMessage($bot, $serviceUrl, $conversationId, ChannelLocale::text($bot->agent, 'This assistant isn’t available on :channel right now.', ['channel' => self::CHANNEL_NAME])))) {
            return;
        }

        $chat = $this->resolver->resolve(
            $bot,
            $conversationId,
            $serviceUrl,
            $activity['conversation']['conversationType'] ?? null,
            $activity['from']['name'] ?? null,
        );

        // Instant feedback: a placeholder activity the moment the message
        // lands. Its id is the handle the reply then streams into.
        $activityId = $this->client->postMessage($bot, $serviceUrl, $conversationId, '_'.ChannelLocale::text($bot->agent, 'Thinking…').'_');

        // Posting failed (bot removed, expired secret, outage) — bail before
        // the pipeline runs so the turn isn't billed for a reply nobody sees.
        if ($activityId === null) {
            return;
        }

        $reply = $this->runner->reply(
            $chat,
            $text,
            fn (string $partial): bool => $this->client->updateMessage($bot, $serviceUrl, $conversationId, $activityId, $partial),
        );

        $this->client->updateMessage($bot, $serviceUrl, $conversationId, $activityId, $reply);

        $chat->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * In channels and group chats the text arrives as `<at>Bot Name</at>
     * question…`. Drop the bot's own mention(s) — identified through the
     * activity's mention entities addressed to the bot (`recipient`) — and
     * unwrap any remaining `<at>` markup around other people's names so the
     * model sees plain text.
     *
     * @param  array<string, mixed>  $activity
     */
    private function extractText(array $activity): string
    {
        $text = $activity['text'] ?? '';

        if (! is_string($text)) {
            return '';
        }

        $recipientId = $activity['recipient']['id'] ?? null;

        foreach ((array) ($activity['entities'] ?? []) as $entity) {
            if (($entity['type'] ?? null) === 'mention'
                && ($entity['mentioned']['id'] ?? null) === $recipientId
                && is_string($entity['text'] ?? null)) {
                $text = str_replace($entity['text'], '', $text);
            }
        }

        $text = preg_replace('#</?at>#', '', $text) ?? $text;

        return trim($text);
    }
}
