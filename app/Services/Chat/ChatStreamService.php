<?php

namespace App\Services\Chat;

use App\Models\AgentQuestionAnswer;
use App\Models\User;
use App\Services\Billing\UserCreditSystem;
use App\Services\Chat\Data\StreamRequestData;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private StreamHandler $streamHandler,
        private UserCreditSystem $creditSystem,
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        $user = $data->agent->user;

        // 0. Check subscription status first
        if (! $this->userIsSubscribed($user)) {
            return $this->streamErrorResponse(
                'Your subscription has expired. Please renew your subscription to continue.',
                'subscription_expired'
            );
        }

        // 1. Check if user can send messages (has remaining messages)
        if (! $this->userCanSendMessages($user->id)) {
            return $this->streamErrorResponse(
                'You have reached your message limit. Please upgrade your plan to continue.',
                'message_limit_reached'
            );
        }

        // 2. Initialize chat context
        $context = $this->chatService->initializeChatContext($data);

        // 3. Enrich context with knowledge (checks Q&A first, then chunks)
        $context = $this->chatService->enrichContextWithKnowledge($context);

        // 4. If Q&A match found, stream it directly without LLM
        if ($context->hasQAMatch()) {
            return $this->streamQAResponse($context);
        }

        // 5. Prepare LLM messages
        $llmMessages = $this->chatService->prepareLlmMessages($context);

        // 6. Create and return the stream response
        return $this->createStreamResponse($context, $llmMessages);
    }

    /**
     * Check if user has an active subscription or is on trial.
     */
    private function userIsSubscribed(User $user): bool
    {
        return $user->subscribed() || $user->onTrial();
    }

    /**
     * Check if the agent owner can send messages (fast cached check).
     */
    private function userCanSendMessages(int $userId): bool
    {
        return $this->creditSystem->setUserId($userId)->canSendMessages();
    }

    /**
     * Stream an error response to the client.
     */
    private function streamErrorResponse(string $message, string $code = 'error'): StreamedResponse
    {
        return new StreamedResponse(function () use ($message, $code) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level()) {
                ob_end_clean();
            }

            echo 'error: '.json_encode(['message' => $message, 'code' => $code])."\n\n";
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream Q&A answer directly to the client
     */
    private function streamQAResponse(ChatContext $context): StreamedResponse
    {
        $qaMatch = $context->qaMatch;
        $answer = $qaMatch->answer;
        $context->appendStreamedMessage($answer);

        return new StreamedResponse(function () use ($context, $answer, $qaMatch) {
            // Setup headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            if (ob_get_level()) {
                ob_end_clean();
            }

            // Stream language
            echo 'language: '.$context->queryLanguage."\n\n";
            flush();

            // Stream the answer in chunks to simulate typing
            $chunks = $this->chunkAnswer($answer);
            $isFirst = true;

            foreach ($chunks as $chunk) {
                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }

                $payload = ['content' => $chunk];

                if ($isFirst) {
                    $payload['message_id'] = $context->assistantMessageId;
                    $isFirst = false;
                }

                echo 'data: '.json_encode($payload)."\n\n";
                flush();

                // Small delay to simulate natural typing
                usleep(15000); // 15ms
            }

            $context->markEndTime();

            // Stream sources if enabled
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

            // Send done signal
            echo "data: {\"content\":\"[DONE]\"}\n\n";
            flush();

            // Handle completion
            $this->chatService->handleChatCompletion($context);

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Split answer into natural chunks for streaming
     */
    private function chunkAnswer(string $answer): array
    {
        // Split by words, keeping punctuation attached
        $words = preg_split('/(\s+)/', $answer, -1, PREG_SPLIT_DELIM_CAPTURE);

        $chunks = [];
        $currentChunk = '';

        foreach ($words as $word) {
            $currentChunk .= $word;

            // Emit chunk every 2-4 words or at sentence boundaries
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

        // Add remaining content
        if (trim($currentChunk) !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Create the streamed response.
     */
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
