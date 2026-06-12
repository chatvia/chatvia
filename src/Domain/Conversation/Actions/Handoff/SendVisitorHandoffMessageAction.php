<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;

class SendVisitorHandoffMessageAction
{
    public function __construct(
        private readonly ChatMemory $chatMemory,
    ) {}

    public function execute(SendVisitorHandoffMessageData $data): ?string
    {
        $conversation = $data->conversation;

        if (! $conversation->isHandedOff()) {
            return null;
        }

        $session = $conversation->session;
        $agent = $session?->agent;

        if (! $agent) {
            return null;
        }

        $messageId = (string) Str::uuid();

        $payload = new ChatInputPayload(
            external_session_id: $session->external_id,
            external_conversation_id: $conversation->external_id,
            external_message_id: $messageId,
            model: $data->model ?? $agent->llm?->external_name ?? 'unknown',
            message: $data->content,
            sent_at: now(),
            ip_address: $data->ipAddress,
            user_agent: $data->userAgent,
            referrer_url: $data->referrerUrl,
            page_url: $data->pageUrl,
            locale: $data->locale,
            source: 'handoff',
        );

        $this->chatMemory
            ->withContext($agent, $session->external_id, $conversation->external_id)
            ->addMessageViaPayload($payload);

        // The widget compares sender_id against its own session UUID
        // (widget.sessionId) to detect its own echoed messages, so broadcast the
        // external session id — never the internal PK.
        MessagePosted::dispatch(
            $conversation->external_id,
            $messageId,
            $data->content,
            'user',
            $conversation->displayName() ?? 'Visitor',
            $session->external_id,
        );

        return $messageId;
    }
}
