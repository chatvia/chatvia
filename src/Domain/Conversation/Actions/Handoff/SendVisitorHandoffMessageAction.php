<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\MessagePosted;
use Illuminate\Support\Str;

/**
 * Delivers a visitor's message to the human operator holding the
 * conversation: adds it to the conversation (live memory now, chat log via
 * the queue) and broadcasts it to the operator. The AI is not involved.
 * Returns the new message id, or null when the conversation is not with a
 * human or its session has no agent any more.
 */
class SendVisitorHandoffMessageAction
{
    /** Sender name shown to the operator when the visitor is anonymous. */
    private const string ANONYMOUS_SENDER = 'Visitor';

    public function __construct(
        private readonly AddMessageToConversationAction $addMessage,
    ) {}

    public function execute(SendVisitorHandoffMessageData $data): ?string
    {
        $conversation = $data->conversation;

        if (! $conversation->isHandedOff()) {
            return null;
        }

        // Everything read below, displayName() included, in one go.
        $conversation->loadMissing(['lead', 'session.agent', 'session.chatter', 'session.user']);

        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        $messageId = (string) Str::uuid7();

        $this->addMessage->execute(
            new ChatScopeData($agent, $session->external_id, $conversation->external_id),
            new UserTurnData(
                external_session_id: $session->external_id,
                external_conversation_id: $conversation->external_id,
                external_message_id: $messageId,
                llm_id: $data->llmId ?? $agent->llm_id,
                message: $data->content,
                sent_at: now(),
                // A signed-in chatter's relayed message still counts against
                // their monthly limit; the session knows who is talking.
                chatter_id: $session->chatter_id,
                ip_address: $data->ipAddress,
                user_agent: $data->userAgent,
                referrer_url: $data->referrerUrl,
                page_url: $data->pageUrl,
                locale: $data->locale,
                source: MessageOrigin::Handoff->value,
            ),
        );

        // The widget compares sender_id with its own session id to spot its
        // own echoed messages, so broadcast the external session id — never
        // the internal key.
        MessagePosted::dispatch(
            $conversation->external_id,
            $messageId,
            $data->content,
            MessageRole::User->value,
            $conversation->displayName() ?? self::ANONYMOUS_SENDER,
            $session->external_id,
        );

        return $messageId;
    }
}
