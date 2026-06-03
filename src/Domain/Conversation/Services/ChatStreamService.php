<?php

namespace Domain\Conversation\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Approval\ResolveToolApprovalData;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\ErrorSource;
use Domain\Conversation\Services\Streaming\Sources\HandoffSource;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\MistralPdfSource;
use Domain\Conversation\Services\Streaming\Sources\QASource;
use Domain\Conversation\Services\Streaming\Sources\ReplaySource;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\SseStreamer;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalStore;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private StreamHandler $streamHandler,
        private UserCreditSystem $creditSystem,
        private SendVisitorHandoffMessageAction $sendVisitorMessage,
        private SseStreamer $streamer,
        private LlmSource $llmSource,
        private MistralPdfSource $mistralPdfSource,
        private QASource $qaSource,
        private HandoffSource $handoffSource,
        private ErrorSource $errorSource,
        private ReplaySource $replaySource,
        private RequestDedupeStore $dedupe,
        private ToolApprovalStore $approvalStore,
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        return config('sse.envelope_v1')
            ? $this->streamWithEnvelope($data)
            : $this->streamLegacy($data);
    }

    /**
     * Resume a paused turn after the user approved/denied the gated tool
     * call(s). Claims the checkpoint exactly once (so a double-submit can't
     * run the tools twice), rebuilds the context from the snapshot, and streams
     * the continuation under the original assistant message id.
     */
    public function resume(ToolApprovalCheckpoint $approval, ResolveToolApprovalData $data): StreamedResponse
    {
        $checkpoint = $this->approvalStore->claim($approval->external_id);

        if (! $checkpoint) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::Internal,
                    'This action was already handled or has expired.',
                    $approval->assistant_message_id,
                )
            );
        }

        $context = $this->chatService->contextForResume($checkpoint);

        return $this->streamer->respond(
            $this->llmSource->resume($context, $checkpoint, $data->decisions)
        );
    }

    // ---------------------------------------------------------------------
    // Envelope v1 path
    // ---------------------------------------------------------------------

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

        // 2. Subscription / credit gates → typed error event.
        if (! $this->userIsSubscribed($user)) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::SubscriptionExpired,
                    'Your subscription has expired. Please renew to continue.',
                )
            );
        }

        if (! $this->userCanSendMessages($user->id)) {
            return $this->streamer->respond(
                $this->errorSource->events(
                    ErrorCode::MessageLimitReached,
                    'You have reached your message limit. Please upgrade to continue.',
                )
            );
        }

        // 3. Build context + knowledge.
        $context = $this->chatService->initializeChatContext($data);
        $context = $this->chatService->enrichContextWithKnowledge($context);

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

    // ---------------------------------------------------------------------
    // Legacy path (pre-envelope) — kept behind the feature flag for safe
    // incremental rollout. To be deleted once envelope_v1 is permanent.
    // ---------------------------------------------------------------------

    private function streamLegacy(StreamRequestData $data): StreamedResponse
    {
        $user = $data->agent->user;

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

                return $this->streamHandoffResponse($conversation);
            }
        }

        if (! $this->userIsSubscribed($user)) {
            return $this->streamErrorResponse(
                'Your subscription has expired. Please renew your subscription to continue.',
                'subscription_expired'
            );
        }

        if (! $this->userCanSendMessages($user->id)) {
            return $this->streamErrorResponse(
                'You have reached your message limit. Please upgrade your plan to continue.',
                'message_limit_reached'
            );
        }

        $context = $this->chatService->initializeChatContext($data);
        $context = $this->chatService->enrichContextWithKnowledge($context);

        if ($context->hasQAMatch()) {
            return $this->streamQAResponse($context);
        }

        $llmMessages = $this->chatService->prepareLlmMessages($context);

        return $this->createStreamResponse($context, $llmMessages);
    }

    private function streamHandoffResponse(Conversation $conversation): StreamedResponse
    {
        return new StreamedResponse(function () use ($conversation): void {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level()) {
                ob_end_clean();
            }

            echo 'data: '.json_encode([
                'type' => 'handoff_ack',
                'status' => 'human',
                'conversation_id' => $conversation->id,
            ])."\n\n";
            flush();

            echo "data: {\"content\":\"[DONE]\"}\n\n";
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function userIsSubscribed(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $billing = $user->billing;

        return (bool) ($billing?->subscribed() || $billing?->onTrial());
    }

    private function userCanSendMessages(int $userId): bool
    {
        return $this->creditSystem->setUserId($userId)->canSendMessages();
    }

    private function streamErrorResponse(string $message, string $code = 'error'): StreamedResponse
    {
        return new StreamedResponse(function () use ($message): void {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level()) {
                ob_end_clean();
            }

            echo 'data: '.json_encode(['content' => $message])."\n\n";
            flush();

            echo "data: {\"content\":\"[DONE]\"}\n\n";
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function streamQAResponse(ChatContext $context): StreamedResponse
    {
        $qaMatch = $context->qaMatch;
        $answer = $qaMatch->answer;
        $context->appendStreamedMessage($answer);

        return new StreamedResponse(function () use ($context, $answer, $qaMatch): void {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level()) {
                ob_end_clean();
            }

            echo 'language: '.$context->queryLanguage."\n\n";
            flush();

            $chunks = $this->chunkAnswer($answer);
            $isFirst = true;

            foreach ($chunks as $chunk) {
                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }

                $payload = ['content' => $chunk];

                if ($isFirst) {
                    $payload['message_id'] = $context->assistantMessageId;
                    if ($context->userMessageId) {
                        $payload['user_message_id'] = $context->userMessageId;
                    }
                    $isFirst = false;
                }

                echo 'data: '.json_encode($payload)."\n\n";
                flush();

                usleep(15000);
            }

            $context->markEndTime();

            if ($context->requestData->streamSources) {
                $sources = [
                    [
                        'id' => $qaMatch->id,
                        'chunk' => $answer,
                        'chunkable_type' => AgentQuestionAnswer::class,
                        'chunkable_id' => $qaMatch->id,
                        'metadata' => ['questions' => $qaMatch->questions, 'source' => 'qa'],
                        'search_type' => 'qa',
                        'confidence_score' => $context->confidenceScore,
                    ],
                ];
                echo 'sources: '.json_encode($sources)."\n\n";
            }

            echo "data: {\"content\":\"[DONE]\"}\n\n";
            flush();

            $this->chatService->handleChatCompletion($context);

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function chunkAnswer(string $answer): array
    {
        $words = preg_split('/(\s+)/', $answer, -1, PREG_SPLIT_DELIM_CAPTURE);

        $chunks = [];
        $currentChunk = '';

        foreach ($words as $word) {
            $currentChunk .= $word;

            if (
                Str::endsWith(trim($currentChunk), ['.', '!', '?', ':', ';']) ||
                str_word_count($currentChunk) >= 3
            ) {
                if (trim($currentChunk) !== '') {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = '';
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    private function createStreamResponse(ChatContext $context, array $llmMessages): StreamedResponse
    {
        return $this->streamHandler->createStreamResponse(
            context: $context,
            llmMessages: $llmMessages,
            onComplete: fn ($ctx) => $this->chatService->handleChatCompletion($ctx),
            onError: fn ($ctx, $e) => $this->chatService->handleClientDisconnection($ctx)
        );
    }
}
