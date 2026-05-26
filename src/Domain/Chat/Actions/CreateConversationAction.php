<?php

namespace Domain\Chat\Actions;

use App\LLM\Operations\QueryRewriter;
use Domain\Chat\Data\ChatInputPayload;
use Domain\Chat\Data\CreateConversationData;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;
use Illuminate\Support\Str;

class CreateConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly ChatSession $session,
        private readonly QueryRewriter $queryRewriter,
    ) {}

    public function execute(CreateConversationData $data): string
    {
        ['lang' => $lang, 'query' => $query] = $this->queryRewriter->rewrite($data->message);

        $sessionId = $this->session->firstOrCreateChatSession($data->agent);

        $conversationId = $this->memory
            ->setAgent($data->agent)
            ->setExternalSessionId($sessionId)
            ->createConversation(title: 'New Chat');

        $payload = ChatInputPayload::from([
            'external_session_id' => $sessionId,
            'external_conversation_id' => $conversationId,
            'external_message_id' => Str::uuid()->toString(),
            'model' => $data->model,
            'message' => $data->message,
            'sent_at' => now(),
            'message_for_llm' => $query,
            'language' => $lang,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
        ]);

        $this->memory->setConversationId($conversationId)
            ->addMessageViaPayload($payload);

        return $conversationId;
    }
}
