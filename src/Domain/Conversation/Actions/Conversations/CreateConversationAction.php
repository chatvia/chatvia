<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Operations\Query\QueryRewriter;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Str;

/**
 * Opens a hosted-chat conversation from the visitor's first message: starts
 * the conversation, records the message (PII masked), and claims the files
 * uploaded before the conversation existed. The answer streams afterwards
 * through the normal turn, which reuses this recorded message.
 */
class CreateConversationAction
{
    /** Model label stored when the agent has no model configured. */
    private const string UNKNOWN_MODEL = 'unknown';

    public function __construct(
        private readonly StartConversationAction $startConversation,
        private readonly AddMessageToConversationAction $addMessage,
        private readonly QueryRewriter $queryRewriter,
        private readonly TokenUsageContext $usage,
        private readonly GuardrailService $guardrails,
    ) {}

    /**
     * @return string the new conversation's external id
     */
    public function execute(CreateConversationData $data): string
    {
        $agent = $data->scope->agent;

        // The first-turn rewrite uses tokens: attribute them to the workspace,
        // as the streaming turn does for its own rewrite and search.
        $rewritten = $this->usage->track(
            $agent->user_id,
            fn (): RewrittenQueryData => $this->queryRewriter->rewrite($data->message),
        );

        $conversationId = $this->startConversation->execute($data->scope, ConversationSource::HostedChat);
        $scope = $data->scope->forConversation($conversationId);

        // PII is masked before anything is stored, so the record and the
        // replayed history never hold a raw identifier. The streaming turn
        // masks the same text the same way, so its match on this first
        // message still lines up. Blocking is left to the streaming turn.
        $this->addMessage->execute($scope, new UserTurnData(
            external_session_id: $data->scope->sessionId,
            external_conversation_id: $conversationId,
            external_message_id: Str::uuid()->toString(),
            model: $agent->llm?->external_name ?? self::UNKNOWN_MODEL,
            message: $this->guardrails->redactPii($agent, $data->message),
            sent_at: now(),
            message_for_llm: $this->guardrails->redactPii($agent, $rewritten->query),
            user_id: $data->chatterUserId,
            language: $rewritten->language,
            ip_address: $data->ipAddress,
            user_agent: $data->userAgent,
            referrer_url: $data->referrerUrl,
            page_url: $data->pageUrl,
            locale: $data->locale,
        ));

        // Files uploaded on the home screen, before the conversation existed,
        // now belong to it; PDFs among them route the next turn to the
        // document LLM.
        $this->claimAttachments($scope->sessionId, $conversationId, $data->attachmentIds, $agent->id);

        return $conversationId;
    }

    /**
     * @param  array<int, string>  $attachmentIds  external ids of orphan rows
     */
    private function claimAttachments(string $sessionId, string $conversationId, array $attachmentIds, int $agentId): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $conversation = Conversation::query()
            ->inSession($sessionId, $agentId)
            ->where('external_id', $conversationId)
            ->first();

        if ($conversation === null) {
            return;
        }

        // The same per-chat ceiling as the upload limit: a script that skipped
        // the composer and uploaded more orphans cannot smuggle them all in.
        $max = (int) config('chatvia.agent.files.upload.max_files');

        Attachment::query()
            ->whereIn('external_id', array_slice($attachmentIds, 0, $max))
            ->whereNull('chat_conversation_id')
            ->update(['chat_conversation_id' => $conversation->id]);
    }
}
