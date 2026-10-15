<?php

namespace Domain\Conversation\Services;

use App\Identity\Workspace\Services\MessageAllowance;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Data\Turn\ReplaySnapshotData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\ErrorSource;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\HandoffSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\PdfSource;
use Domain\Conversation\Services\Streaming\Sources\QaSource;
use Domain\Conversation\Services\Streaming\Sources\ReplaySource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Conversation\Services\Turn\TurnContextFactory;
use Generator;

/**
 * Decides how a web chat turn is answered and hands back its events. The
 * checks and the context build run right away — before the HTTP response
 * starts — and the chosen source's generator streams the rest (the App layer
 * turns the events into server-sent events). In order:
 *
 *   1. a deactivated workspace's agent answers nothing ({@see WorkspaceActivation});
 *   2. a retried request replays its finished answer;
 *   3. a conversation held by a human operator relays the message to them;
 *   4. the workspace's and the chatter's monthly message limits and the
 *      agent's rate limit can refuse the turn with a typed error;
 *   5. otherwise the turn is built and answered by the guardrail refusal, an
 *      exact Q&A match, the document LLM (PDF conversations) or the agent's
 *      LLM with its tools.
 */
readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private TurnContextFactory $contexts,
        private MessageAllowance $allowance,
        private SendVisitorHandoffMessageAction $sendVisitorMessage,
        private LlmSource $llmSource,
        private PdfSource $pdfSource,
        private QaSource $qaSource,
        private GuardBlockSource $guardBlockSource,
        private HandoffSource $handoffSource,
        private ErrorSource $errorSource,
        private ReplaySource $replaySource,
        private RequestDedupeStore $dedupe,
        private ToolApprovalStore $approvalStore,
        private AgentChatRateLimiter $rateLimiter,
        private WorkspaceActivation $activation,
    ) {}

    /**
     * @return Generator<int, StreamEvent>
     */
    public function stream(StreamRequestData $data): Generator
    {
        // The hosted chat and widget middleware already turn these visitors
        // away; this keeps the engine closed to any other caller too. The
        // messaging channels send the same text.
        if (! $this->activation->isActive($data->agent->workspace_id)) {
            return $this->errorSource->events(ErrorCode::Internal, __('Sorry — this assistant is unavailable right now. Please try again later.'));
        }

        $replay = $this->replay($data);

        if ($replay instanceof Generator) {
            return $replay;
        }

        $conversation = $this->conversation($data);

        if ($conversation?->isHandedOff()) {
            return $this->relayToOperator($data, $conversation);
        }

        return $this->refusal($data) ?? $this->answer($data, $conversation);
    }

    /**
     * Resume a turn paused for approval after the person approved or denied
     * the gated tool calls. The checkpoint is claimed exactly once (so a
     * double submit cannot run the tools twice), the context is rebuilt from
     * it, and the continuation streams under the original assistant message
     * id — on the same source the paused turn used: the document LLM while the
     * conversation still has PDFs, the agent's LLM otherwise.
     *
     * @return Generator<int, StreamEvent>
     */
    public function resume(ToolApprovalCheckpoint $approval, ResolveToolApprovalData $data): Generator
    {
        $checkpoint = $this->approvalStore->claim($approval->external_id);

        if (! $checkpoint instanceof ToolApprovalCheckpoint) {
            return $this->errorSource->events(
                ErrorCode::Internal,
                __('This action was already handled or has expired.'),
                $approval->assistant_message_id,
            );
        }

        $context = $this->contexts->forResume($checkpoint);
        $conversation = $this->conversation($context->requestData);

        if ($conversation instanceof Conversation && $conversation->pdfAttachments()->exists()) {
            return $this->pdfSource->resume($context, $checkpoint, $data->decisions);
        }

        return $this->llmSource->resume($context, $checkpoint, $data->decisions);
    }

    /**
     * @return Generator<int, StreamEvent>|null
     */
    private function replay(StreamRequestData $data): ?Generator
    {
        if (! $data->requestId) {
            return null;
        }

        $snapshot = $this->dedupe->find($data->requestId);

        return $snapshot instanceof ReplaySnapshotData && $snapshot->content !== ''
            ? $this->replaySource->events($data->requestId, $snapshot)
            : null;
    }

    /**
     * A human operator holds the conversation: deliver the message to them
     * and acknowledge it — the AI does not answer.
     *
     * @return Generator<int, StreamEvent>
     */
    private function relayToOperator(StreamRequestData $data, Conversation $conversation): Generator
    {
        if ($data->message->message !== '') {
            $this->sendVisitorMessage->execute(new SendVisitorHandoffMessageData(
                conversation: $conversation,
                content: $data->message->message,
                model: $data->message->model->external_name,
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent,
                referrerUrl: $data->referrerUrl,
                pageUrl: $data->pageUrl,
                locale: $data->locale,
            ));
        }

        return $this->handoffSource->events($conversation);
    }

    /**
     * The checks that refuse a turn before any model work. The workspace's
     * monthly limit counts the replies it receives; the messaging channels
     * check the same limit.
     *
     * @return Generator<int, StreamEvent>|null
     */
    private function refusal(StreamRequestData $data): ?Generator
    {
        if (! $this->allowance->canSend($data->agent->workspace_id)) {
            return $this->errorSource->events(ErrorCode::MessageLimitReached, __('This agent has reached its monthly message limit. Please try again next month.'));
        }

        // The chatter's own monthly limit, on top of the workspace's. Only
        // signed-in chatters can have one.
        if ($data->chatterId !== null && ! $this->allowance->canSendAsChatter($data->chatterId)) {
            return $this->errorSource->events(ErrorCode::ChatterMessageLimitReached, __('You have reached your monthly message limit.'));
        }

        // The agent's owner-configured messages-per-minute cap, across all
        // visitors. Retryable; the owner may be alerted by email.
        if ($this->rateLimiter->exceeded($data->agent)) {
            return $this->errorSource->events(ErrorCode::RateLimited, __('This agent is receiving too many messages right now. Please try again in a moment.'));
        }

        return null;
    }

    /**
     * Build the turn and pick the source that answers it.
     *
     * @return Generator<int, StreamEvent>
     */
    private function answer(StreamRequestData $data, ?Conversation $conversation): Generator
    {
        $context = $this->chatService->enrichContextWithKnowledge($this->chatService->initializeChatContext($data));

        // An input guardrail refused the message: no model call, not counted.
        if ($context->guardBlocked) {
            return $this->guardBlockSource->events($context);
        }

        if ($context->hasQaMatch()) {
            return $this->qaSource->events($context);
        }

        $llmMessages = $this->chatService->prepareLlmMessages($context);

        // A conversation with PDFs is answered by the document LLM, which
        // reads the whole files, instead of the agent's LLM. The rows come
        // from the upload endpoints (claimed by the conversation on its first
        // hosted-chat turn).
        $pdfs = ($conversation ?? $this->conversation($data))?->pdfAttachments()->get();

        if ($pdfs !== null && $pdfs->isNotEmpty()) {
            return $this->pdfSource->events($context, $llmMessages, $pdfs);
        }

        return $this->llmSource->events($context, $llmMessages);
    }

    /**
     * The turn's conversation, only within the caller's own session of this
     * agent — a conversation id from another visitor never matches.
     */
    private function conversation(StreamRequestData $data): ?Conversation
    {
        if ($data->conversationId === '') {
            return null;
        }

        return Conversation::query()
            ->inSession($data->externalSessionId, $data->agent->id)
            ->where('external_id', $data->conversationId)
            ->first();
    }
}
