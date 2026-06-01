<?php

namespace Domain\Chat\Actions\Handoff;

use Domain\Chat\Data\Messages\ChatInputPayload;
use Domain\Chat\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Chat\Events\ChatMessagePosted;
use Domain\Chat\Services\Memory\ChatMemory;
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
            ->setAgent($agent)
            ->setExternalSessionId($session->external_id)
            ->setConversationId($conversation->external_id)
            ->addMessageViaPayload($payload);

        ChatMessagePosted::dispatch(
            $conversation->external_id,
            $messageId,
            $data->content,
            'user',
            $conversation->displayName() ?? 'Visitor',
            (string) $session->id,
        );

        return $messageId;
    }
}
