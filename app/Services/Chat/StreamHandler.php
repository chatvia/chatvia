<?php

namespace App\Services\Chat;

use App\Contracts\LLMProviderInterface;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamHandler
{
    public function __construct(
        private LLMProviderInterface $llmProvider
    ) {}

    private bool $isClientConnected = true;

    public function createStreamResponse(
        ChatContext $context,
        array $llmMessages,
        ?callable $onComplete = null,
        ?callable $onError = null
    ): StreamedResponse {
        return new StreamedResponse(function () use ($context, $llmMessages, $onComplete, $onError) {
            $this->setupStreamHeaders();

            ignore_user_abort(false);

            try {
                $response = $this->llmProvider->stream(
                    $context->requestData->message->model->internal_name,
                    $llmMessages,
                    $context->requestData->agent->llmSettings()
                );

                $this->processStreamResponse($response, $context);

                $this->handleStreamCompletion($context);

                if ($onComplete) {
                    $onComplete($context);
                }

            } catch (RequestException $e) {
                $this->handleRequestException($e, $context);

                if ($onError) {
                    $onError($context, $e);
                }
            }
        }, 200, $this->getResponseHeaders());
    }

    private function setupStreamHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        flush();

        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    private function processStreamResponse(ResponseInterface $response, ChatContext $context): void
    {
        $buffer = '';
        $body = $response->getBody();

        echo 'language: '.$context->queryLanguage."\n\n";

        while (! $body->eof()) {
            if (connection_status() !== CONNECTION_NORMAL) {
                $this->isClientConnected = false;
                break;
            }

            $chunk = $body->read(1024);
            $buffer .= $chunk;

            $lineNumber = 0;
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (trim($line) === '') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $this->processDataLine(
                        $lineNumber,
                        substr($line, 6),
                        $context
                    );
                }

                $lineNumber++;
            }
        }

        flush();
    }

    private function processDataLine(int $lineNumber, string $data, ChatContext $context): void
    {
        if ($data === '[DONE]') {
            $this->handleDoneMessage($context);

            return;
        }

        try {
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (isset($json['choices'][0]['delta']['content'])) {
                $content = $json['choices'][0]['delta']['content'];
                $context->appendStreamedMessage($content);

                $messagePayload = ['content' => $content];

                if ($lineNumber === 0) {
                    $messagePayload['message_id'] = $context->assistantMessageId;
                    if ($context->userMessageId) {
                        $messagePayload['user_message_id'] = $context->userMessageId;
                    }
                }

                echo 'data: '.json_encode($messagePayload, JSON_THROW_ON_ERROR)."\n\n";
                flush();
            }

            // Extract generation ID from the response
            if (isset($json['id']) && is_null($context->generationId)) {
                $context->generationId = $json['id'];
            }

        } catch (Exception $e) {
            Log::error('Error processing SSE chunk: '.$e->getMessage());
        }
    }

    private function handleDoneMessage(ChatContext $context): void
    {
        $context->markEndTime();

        if ($context->requestData->streamSources) {
            $sources = $context->sources->toArray();
            echo 'sources: '.json_encode($sources, JSON_THROW_ON_ERROR)."\n\n";
        }

        echo 'data: '.json_encode(['content' => '[DONE]'])."\n\n";
        flush();
    }

    private function handleStreamCompletion(ChatContext $context): void
    {
        if (! $this->isClientConnected && $context->userMessageId) {
            // Will be handled by the onError callback
        }
    }

    private function handleRequestException(RequestException $e, ChatContext $context): void
    {
        echo 'data: '.json_encode(['content' => 'Could not connect to the selected model'], JSON_THROW_ON_ERROR)."\n\n";
        echo "data: {\"content\":\"[DONE]\"}\n\n";
        flush();

        report($e);

        Log::error('Chat stream error: '.$e->getMessage());
    }

    private function getResponseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
