<?php

namespace Domain\Conversation\Services;

use Domain\Billing\Enums\MessageAdmission;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Billing\Services\MessageSendGate;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\ErrorSource;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sources\HandoffSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\MistralPdfSource;
use Domain\Conversation\Services\Streaming\Sources\QASource;
use Domain\Conversation\Services\Streaming\Sources\ReplaySource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\SseStreamer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Llm\Usage\TokenUsageContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private MessageSendGate $sendGate,
        private ChatterMessageAllowance $chatterAllowance,
        private SendVisitorHandoffMessageAction $sendVisitorMessage,
        private SseStreamer $streamer,
        private LlmSource $llmSource,
        private MistralPdfSource $mistralPdfSource,
        private QASource $qaSource,
        private GuardBlockSource $guardBlockSource,
        private HandoffSource $handoffSource,
        private ErrorSource $errorSource,
        private ReplaySource $replaySource,
        private RequestDedupeStore $dedupe,
        private ToolApprovalStore $approvalStore,
        private TokenUsageContext $usage,
        private AgentChatRateLimiter $rateLimiter,
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        return $this->streamWithEnvelope($data);
    }

    /**
     * Resume a paused turn after the user approved/denied the gated tool
     * call(s). Claims the checkpoint exactly once (so a double-submit can't
     * run the tools twice), rebuilds the context from the snapshot, and streams
     * the continuation under the original assistant message id.
     *
     * Picks the same source the paused turn used: Mistral PDF if the
     * conversation still has PDF attachments, the agent LLM otherwise.
     */
    public function resume(ToolApprovalCheckpoint $approval, ResolveToolApprovalData $data): StreamedResponse
    {
        $checkpoint = $this->approvalStore->claim($approval->external_id);

        if (! $checkpoint) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::Internal,
                    __('This action was already handled or has expired.'),
                    $approval->assistant_message_id,
                )
            );
        }

        $context = $this->chatService->contextForResume($checkpoint);

        $conversation = $context->requestData->conversationId
            ? Conversation::where('external_id', $context->requestData->conversationId)->first()
            : null;

        if ($conversation && $conversation->pdfAttachments()->exists()) {
            return $this->streamer->respond(
                $this->mistralPdfSource->resume($context, $checkpoint, $data->decisions)
            );
        }

        return $this->streamer->respond(
            $this->llmSource->resume($context, $checkpoint, $data->decisions)
        );
    }

    private function streamWithEnvelope(StreamRequestData $data): StreamedResponse
    {
        // 0. Idempotent replay of a previously-completed request.
        if ($data->requestId) {
            $snapshot = $this->dedupe->find($data->requestId);
            if ($snapshot && ($snapshot['content'] ?? '') !== '') {
                return $this->streamer->respond(
                    $this->replaySource->events($data->requestId, $snapshot)
                );
            }
        }

        // 1. Conversation handed off to a human — acknowledge and broadcast.
        if ($data->conversationId) {
            $conversation = Conversation::where('external_id', $data->conversationId)->first();
            if ($conversation && $conversation->isHandedOff()) {
                if ($data->message->message) {
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

                return $this->streamer->respond(
                    $this->handoffSource->events($conversation)
                );
            }
        }

        $user = $data->agent->user;

        // 2. Subscription / credit gates → typed error event. One shared gate
        // (MessageSendGate) so the messaging channels apply the same rule.
        $admission = $this->sendGate->admit($user);

        if ($admission === MessageAdmission::SubscriptionInactive) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::SubscriptionExpired,
                    __('Your subscription has expired. Please renew to continue.'),
                )
            );
        }

        // Credit gate — message-based. Billing meters the assistant replies a
        // workspace receives; this blocks when the cycle's message quota is
        // exhausted or there's no active billing cycle. (Tokens are analytics
        // only — see TokenUsageContext — and never gate.)
        if ($admission === MessageAdmission::CreditsExhausted) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::MessageLimitReached,
                    __('You have reached your message limit. Please upgrade to continue.'),
                )
            );
        }

        // 2b. Per-chatter monthly limit — independent of the owner gate above.
        // Only set for authenticated chatters (widget + hosted chat); an absent
        // or fresh-month allowance passes (the write path provisions it).
        if ($data->chatterUserId !== null && $this->chatterLimitReached($data->chatterUserId)) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::ChatterMessageLimitReached,
                    __('You have reached your monthly message limit.'),
                )
            );
        }

        // 2c. Per-agent chat rate limit — owner-configurable on the Security
        // page. Caps answered messages per minute across all visitors before
        // any LLM work; over the cap we return a retryable error and (when the
        // owner opted in) alert them by email via AgentRateLimitExceeded.
        if ($this->rateLimiter->exceeded($data->agent)) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::RateLimited,
                    __('This agent is receiving too many messages right now. Please try again in a moment.'),
                )
            );
        }

        // 3. Build context + knowledge. The query rewrite, query embedding, and
        // reranking done here all consume tokens — attribute them to the workspace
        // for analytics. (The streamed reply itself reports usage out-of-band and
        // is recorded separately by StoreMessageAction.)
        $context = $this->usage->track($data->agent->user_id, function () use ($data) {
            $context = $this->chatService->initializeChatContext($data);

            return $this->chatService->enrichContextWithKnowledge($context);
        }, 'chat');

        // 3b. Input guardrail blocked the message (injection / off-topic / toxic)
        // → stream the refusal instead of calling the LLM. No model call, no credit.
        if ($context->guardBlocked) {
            return $this->streamer->respond(
                $this->guardBlockSource->events($context)
            );
        }

        // 4. Q&A match → stream the answer using the same envelope.
        if ($context->hasQAMatch()) {
            return $this->streamer->respond(
                $this->qaSource->events($context)
            );
        }

        // 5. Prepare the LLM messages once — both branches consume them.
        $llmMessages = $this->chatService->prepareLlmMessages($context);

        // 5a. PDF chat — when the conversation has any PDF attachments,
        // hand off to Mistral's native document Q&A instead of running the
        // agent's LLM + MCP tool loop. Rows are created by the upload
        // endpoints (and claimed by CreateConversationAction on the first
        // Home.vue turn).
        $conversation = $conversation ?? (
            $data->conversationId
                ? Conversation::where('external_id', $data->conversationId)->first()
                : null
        );

        if ($conversation) {
            $pdfs = $conversation->pdfAttachments()->get();
            if ($pdfs->isNotEmpty()) {
                return $this->streamer->respond(
                    $this->mistralPdfSource->events($context, $llmMessages, $pdfs)
                );
            }
        }

        // 5b. Default — agent LLM stream with MCP tool loop.
        return $this->streamer->respond(
            $this->llmSource->events($context, $llmMessages)
        );
    }

    private function chatterLimitReached(int $chatterUserId): bool
    {
        // Cached yes/no gate (mirrors the owner's UserCreditSystem) — a cache hit
        // costs zero DB queries; the user is only loaded on a miss.
        return ! $this->chatterAllowance->canSendMessagesById($chatterUserId);
    }
}
