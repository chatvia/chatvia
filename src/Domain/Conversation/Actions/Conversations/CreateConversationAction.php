<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Support\Str;

class CreateConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly TurnState $session,
        private readonly QueryRewriter $queryRewriter,
    ) {}

    public function execute(CreateConversationData $data): string
    {
        ['lang' => $lang, 'query' => $query] = $this->queryRewriter->rewrite($data->message);

        $sessionId = $this->session->firstOrCreateSession($data->agent);

        $conversationId = $this->memory
            ->withContext($data->agent, $sessionId)
            ->createConversation(title: 'New Chat', source: ConversationSource::HostedChat);

        $payload = ChatInputPayload::from([
            'external_session_id' => $sessionId,
            'external_conversation_id' => $conversationId,
            'external_message_id' => Str::uuid()->toString(),
            'model' => $data->agent->llm?->external_name ?? 'unknown',
            'message' => $data->message,
            'sent_at' => now(),
            'message_for_llm' => $query,
            'user_id' => $data->chatterUserId,
            'language' => $lang,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'referrer_url' => $data->referrerUrl,
            'page_url' => $data->pageUrl,
            'locale' => $data->locale,
        ]);

        $this->memory->setConversationId($conversationId)
            ->addMessageViaPayload($payload);

        // Bind orphan attachment rows (uploaded before the conversation
        // existed, on the Home.vue path) to this new conversation. The
        // streaming pipeline will pick up any PDFs as `pdfAttachments` and
        // route the next turn through MistralPdfSource.
        $this->claimAttachments($conversationId, $data->attachmentIds);

        return $conversationId;
    }

    /**
     * @param  array<int, string>  $attachmentIds  External UUIDs of orphan rows.
     */
    private function claimAttachments(string $conversationExternalId, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $conversation = Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->first();

        if (! $conversation) {
            return;
        }

        // Cap orphan claims at the same per-chat ceiling enforced by
        // MaxConversationAttachments — otherwise a script that bypassed
        // the composer and uploaded N orphans pre-conversation could
        // smuggle them all in here, defeating the upload-side limit on
        // every subsequent turn.
        $max = (int) config('chatvia.agent.files.upload.max_files');
        $attachmentIds = array_slice($attachmentIds, 0, $max);

        Attachment::query()
            ->whereIn('external_id', $attachmentIds)
            ->whereNull('chat_conversation_id')
            ->update(['chat_conversation_id' => $conversation->id]);
    }
}
