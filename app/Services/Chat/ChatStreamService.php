<?php

namespace App\Services\Chat;

use App\Models\AgentQuestionAnswer;
use App\Services\Chat\Data\StreamRequestData;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class ChatStreamService
{
    public function __construct(
        private ChatService $chatService,
        private StreamHandler $streamHandler,
    ) {}

    public function stream(StreamRequestData $data): StreamedResponse
    {
        // 1. Initialize chat context
        $context = $this->chatService->initializeChatContext($data);

        // 2. Enrich context with knowledge (checks Q&A first, then chunks)
        $context = $this->chatService->enrichContextWithKnowledge($context);

        // 3. If Q&A match found, stream it directly without LLM
        if ($context->hasQAMatch()) {
            return $this->streamQAResponse($context);
        }

        // 4. Prepare LLM messages
        $llmMessages = $this->chatService->prepareLlmMessages($context);

        // 5. Create and return the stream response
        return $this->createStreamResponse($context, $llmMessages);
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
