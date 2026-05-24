<?php

namespace Domain\Chat\Services;

use Domain\Sources\QA\Models\AgentQuestionAnswer;
use Domain\Chat\Models\ChatConversation;
use App\Auth\Models\User;
use App\Billing\Services\UserCreditSystem;
use Domain\Chat\Services\Data\StreamRequestData;
use Domain\Chat\Services\Streaming\ErrorCode;
use Domain\Chat\Services\Streaming\SseStreamer;
use Domain\Chat\Services\Streaming\Sources\ErrorSource;
use Domain\Chat\Services\Streaming\Sources\HandoffSource;
use Domain\Chat\Services\Streaming\Sources\LlmSource;
use Domain\Chat\Services\Streaming\Sources\QASource;
use Domain\Chat\Services\Streaming\Sources\ReplaySource;
use Domain\Chat\Services\Streaming\RequestDedupeStore;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private StreamHandler $streamHandler,
        private UserCreditSystem $creditSystem,
        private HandoffService $handoffService,
        private SseStreamer $streamer,
        private LlmSource $llmSource,
        private QASource $qaSource,
        private HandoffSource $handoffSource,
        private ErrorSource $errorSource,
        private ReplaySource $replaySource,
        private RequestDedupeStore $dedupe,
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        return config('sse.envelope_v1')
            ? $this->streamWithEnvelope($data)
            : $this->streamLegacy($data);
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
            $conversation = ChatConversation::where('external_id', $data->conversationId)->first();
            if ($conversation && $this->handoffService->isHandedOff($conversation)) {
                if ($data->message->message) {
                    $this->handoffService->sendUserMessage(
                        conversation: $conversation,
                        content: $data->message->message,
                        model: $data->message->model?->external_name,
                        ipAddress: $data->ipAddress,
                        userAgent: $data->userAgent,
                        referrerUrl: $data->referrerUrl,
                        pageUrl: $data->pageUrl,
                        locale: $data->locale,
                    );
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

        // 5. Default — LLM stream.
        $llmMessages = $this->chatService->prepareLlmMessages($context);

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
            $conversation = ChatConversation::where('external_id', $data->conversationId)->first();
            if ($conversation && $this->handoffService->isHandedOff($conversation)) {
                if ($data->message->message) {
                    $this->handoffService->sendUserMessage(
                        conversation: $conversation,
                        content: $data->message->message,
                        model: $data->message->model?->external_name,
                        ipAddress: $data->ipAddress,
                        userAgent: $data->userAgent,
                        referrerUrl: $data->referrerUrl,
                        pageUrl: $data->pageUrl,
                        locale: $data->locale,
                    );
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

    private function streamHandoffResponse(ChatConversation $conversation): StreamedResponse
    {
        return new StreamedResponse(function () use ($conversation) {
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
        return $user->isAdmin() || $user->subscribed() || $user->onTrial();
    }

    private function userCanSendMessages(int $userId): bool
    {
        return $this->creditSystem->setUserId($userId)->canSendMessages();
    }

    private function streamErrorResponse(string $message, string $code = 'error'): StreamedResponse
    {
        return new StreamedResponse(function () use ($message) {
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

        return new StreamedResponse(function () use ($context, $answer, $qaMatch) {
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
