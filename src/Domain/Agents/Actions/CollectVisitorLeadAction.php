<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Data\CollectLeadData;
use Domain\Agents\Models\Llm;
use Domain\Chat\Data\ChatOutputPayload;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Services\Memory\ChatMemory;
use Illuminate\Support\Str;

class CollectVisitorLeadAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    /**
     * Returns true if a new lead was recorded; false if the email already had
     * a lead (idempotent).
     */
    public function execute(CollectLeadData $data): bool
    {
        $agent = $data->agent;

        if ($agent->leads()->where('email', $data->email)->exists()) {
            return false;
        }

        $conversationId = ChatConversation::where('external_id', $data->threadId)->value('id');

        $agent->leads()->create([
            'chat_conversation_id' => $conversationId,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'job_title' => $data->jobTitle,
            'company' => $data->company,
            'location' => $data->location,
            'message' => $data->message,
            'ip_address' => $data->ipAddress,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
            'user_agent' => $data->userAgent,
        ]);

        /** @var Llm $llm */
        $llm = $agent->llms()->first();

        $this->memory
            ->setAgent($agent)
            ->setExternalSessionId($data->sessionId)
            ->setConversationId($data->threadId)
            ->addMessageViaPayload(new ChatOutputPayload(
                external_session_id: $data->sessionId,
                external_conversation_id: $data->threadId,
                external_message_id: Str::uuid()->toString(),
                message: 'We have received your contact information. We will get back to you shortly. Thank you!',
                model: $llm->internal_name,
                sent_at: now(),
            ));

        return true;
    }
}
