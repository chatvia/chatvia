<?php

namespace Domain\Conversation\Services\Turn;

use Carbon\Carbon;
use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Query\QueryRewriter;
use Illuminate\Support\Str;

/**
 * Builds the {@see ChatContext} a turn runs on. For a new turn it rewrites
 * the visitor's message for retrieval, runs the input guardrails (PII masking,
 * injection / topic / toxicity blocking), records the message in the
 * conversation, and loads the history the LLM will see. For a resumed turn it
 * restores everything from the approval checkpoint instead.
 */
class TurnContextFactory
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly AddMessageToConversationAction $addMessage,
        private readonly QueryRewriter $queryRewriter,
        private readonly GuardrailService $guardrails,
        private readonly GuardrailRecorder $guardrailRecorder,
    ) {}

    public function forNewTurn(StreamRequestData $data): ChatContext
    {
        $scope = new ChatScopeData($data->agent, $data->externalSessionId, $data->conversationId);
        $memory = $this->memory->withScope($scope);

        $history = $memory->getLlmMessages();

        // The guard runs first: a blocked turn needs no rewrite, and the
        // rewrite reads the masked text, never a raw identifier.
        $guard = $this->inspectInput($data);
        $rewritten = $guard->blocked
            ? RewrittenQueryData::unchanged($guard->text)
            : $this->queryRewriter->rewrite($guard->text, $this->earlierTurns($history, $data));
        $turn = $this->userTurn($data, $guard, $rewritten);

        // A follow-up message is recorded now. The first message of a new
        // conversation was already recorded when the conversation was created,
        // so its existing id is reused — otherwise the `start` event would
        // hand the client an id that exists nowhere, and a later regenerate
        // or edit would send that stale id back.
        if ($data->saveUserMessageImmediately || count($history) > 1) {
            $this->addMessage->execute($scope, $turn);
        } else {
            $turn->external_message_id = $this->recordedFirstMessageId($memory, $turn) ?? $turn->external_message_id;
        }

        $context = new ChatContext(
            requestData: $data,
            payload: $turn,
            llmMessages: $history,
            userMessageId: $turn->external_message_id,
            assistantMessageId: Str::uuid7()->toString(),
            queryLanguage: $rewritten->language,
            searchQuery: $rewritten->query,
        );

        // Carry the block decision to the stream, and log every input verdict.
        // The masked text is recorded, never the raw message, so the audit
        // snippet cannot itself hold PII.
        $context->guardBlocked = $guard->blocked;
        $context->guardRefusal = $guard->refusalMessage;
        $this->guardrailRecorder->record($context, $turn->message, $guard);

        return $context;
    }

    /**
     * Rebuild the context of a turn paused for tool approval so the tool loop
     * can resume. No message is recorded and no rewrite runs: the LLM history
     * comes back verbatim from the snapshot and the existing assistant message
     * id is reused. The answer buffer starts empty, so the saved message is
     * the post-approval answer — the pre-approval preface only ever shows
     * live, like any tool-call preface.
     */
    public function forResume(ToolApprovalCheckpoint $checkpoint): ChatContext
    {
        $saved = $checkpoint->approvalContext();
        $model = Llm::findOrFail($saved->modelId);

        $requestData = new StreamRequestData(
            agent: $checkpoint->agent,
            message: new StreamRequestMessageData(model: $model, message: $saved->userMessage),
            externalSessionId: $saved->externalSessionId,
            conversationId: $checkpoint->conversation_external_id,
            chatterId: $saved->chatterId,
            chatterName: $saved->chatterName,
            actorUserId: $saved->actorUserId,
            actorName: $saved->actorName,
            streamSources: $saved->streamSources,
            source: $saved->source,
            requestId: $checkpoint->request_id,
        );

        $turn = new UserTurnData(
            external_session_id: $saved->externalSessionId,
            external_conversation_id: $checkpoint->conversation_external_id,
            external_message_id: $checkpoint->assistant_message_id,
            model: $model->external_name,
            message: $saved->userMessage,
            sent_at: now(),
            language: $saved->queryLanguage,
            source: $saved->source,
        );

        return new ChatContext(
            requestData: $requestData,
            payload: $turn,
            llmMessages: $checkpoint->messages_snapshot,
            userMessageId: null,
            assistantMessageId: $checkpoint->assistant_message_id,
            queryLanguage: $saved->queryLanguage,
        );
    }

    /**
     * The chat before this message, so the rewriter can turn a follow-up such
     * as "and the price?" into a full question. The first message of a new
     * conversation is already in the history: it has no chat before it.
     *
     * @param  list<array{role: string, content: mixed}>  $history
     * @return list<array{role: string, content: mixed}>
     */
    private function earlierTurns(array $history, StreamRequestData $data): array
    {
        if (! $data->saveUserMessageImmediately && count($history) <= 1) {
            return [];
        }

        return $history;
    }

    /**
     * Input guardrails. A turn that opts out of guards (evals) gets the
     * message back untouched.
     */
    private function inspectInput(StreamRequestData $data): GuardResultData
    {
        return $data->guardrailsEnabled
            ? $this->guardrails->inspectInput($data->agent, $data->message->message)
            : GuardResultData::clean($data->message->message);
    }

    /**
     * The visitor's message as the turn carries it. The guarded text has PII
     * masked (it equals the original when there is none) and is what gets
     * stored, shown, replayed and answered, so a raw identifier never reaches
     * the record or the model. The rewritten question only serves the search
     * (ChatContext::$searchQuery). PDFs travel separately (see PdfSource), so
     * the visible text stays clean.
     */
    private function userTurn(StreamRequestData $data, GuardResultData $guard, RewrittenQueryData $rewritten): UserTurnData
    {
        $storedMessage = $guard->text;

        return new UserTurnData(
            external_session_id: $data->externalSessionId,
            external_conversation_id: $data->conversationId,
            external_message_id: Str::uuid7()->toString(),
            model: $data->message->model->external_name,
            message: $storedMessage,
            sent_at: $data->message->createdAt ? Carbon::parse($data->message->createdAt) : now(),
            message_for_llm: $storedMessage,
            chatter_id: $data->chatterId,
            language: $rewritten->language,
            ip_address: $data->ipAddress,
            country: $data->country,
            user_agent: $data->userAgent,
            referrer_url: $data->referrerUrl,
            page_url: $data->pageUrl,
            locale: $data->locale,
            source: $data->source,
            attachments: $data->message->attachments,
        );
    }

    /**
     * The id of the already-recorded first message. It was stored masked, so
     * it is matched on the masked text, not the raw original.
     */
    private function recordedFirstMessageId(ChatMemory $memory, UserTurnData $turn): ?string
    {
        $recorded = collect($memory->getMessagesForConversation())->last(
            static fn (array $message): bool => $message['role'] === MessageRole::User->value && $message['content'] === $turn->message,
        );

        return $recorded['id'] ?? null;
    }
}
