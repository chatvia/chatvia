<?php

namespace App\Http\Controllers\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Rules\ModelRule;
use App\Support\ChatMemory;
use App\Support\KnowledgeBase;
use App\Support\Models;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController
{
    private Agent $agent;

    private ChatInputPayload $payload;

    private bool $isClientConnected = true;

    private string $streamedMessage = '';

    private array $llmMessages = [];

    private array $llmModel = [];

    private ?string $userMessageId;

    private float $startTime;

    private ?string $generationId = null;

    private ?int $tokenCount = 0;

    public function __construct(
        private readonly Client        $httpClient,
        private readonly ChatMemory    $memory,
        private readonly KnowledgeBase $knowledgeBase) {}

    public function __invoke(Request $request, Models $models, string $conversationId)
    {
        $request->validate([
            'model' => ['required','string', new ModelRule],
            'message' => ['required','string'],
            'updated_message_info' => ['nullable', 'array'],
        ]);

        $agent = current_agent();

        abort_if(boolean: ! $agent, code: 404);

        $this->agent = $agent;

        $this->memory
            ->setConversationId($conversationId)
            ->setAgent($agent);

        $this->payload = ChatInputPayload::fromRequest($request);

        $this->llmMessages = $this->memory->getLlmMessages();

        $this->llmModel = $models->getModelByExternalId($this->payload->model);

        $this->userMessageId = null;

        // Do not add a new message if the total number of messages is greater than 1
        // Because when we create a conversation, a new message will be added automatically.
        if (count($this->llmMessages) > 1) {
            $this->userMessageId = $this->memory->addMessageViaPayload($this->payload);
        }

        $this->startTime = microtime(true);

        return $this->createStreamResponse();
    }


    private function createStreamResponse(): StreamedResponse
    {
        return new StreamedResponse(function ()  {
            $this->setupStreamHeaders();

            // Set up client cancellation detection
            ignore_user_abort(false);

            try {
                $llmMessages = $this->prepareLlmMessages($this->llmMessages);
                $response = $this->makeOpenRouterRequest($llmMessages);
                $this->processStreamResponse(
                    $response,
                );

                $this->handleCompletedResponse();

            } catch (RequestException $e) {
                $this->handleRequestException($e);
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

    private function prepareLlmMessages(array $messages): array
    {
        $agent = $this->agent;

        $systemPrompt = [
            'role' => 'system',
            'content' => $agent->system_instructions,
        ];

        $userMessageContent = $this->payload->message;

        if ($agent->isKnowledgeable()) {
            $lastMessage = $this->memory->getLastMessageForLlm();
            $lastMessageContent = $lastMessage['content'] ?? '';

            if ($this->knowledgeBase->shouldContinueSearching($lastMessageContent, $userMessageContent)) {
                $userMessageContent = Str::of("### User Question:\n")
                    ->append($this->payload->message_for_llm)
                    ->append("### Retrieved Context\n")
                    ->append($this->knowledgeBase->chunks($agent, $userMessageContent))
                    ->toString();
            }
        }

        $userMessage = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    private function makeOpenRouterRequest(array $llmMessages): ResponseInterface
    {
        return $this->httpClient->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->llmModel['id'],
                'messages' => $llmMessages,
                'stream' => true,
            ],
            'stream' => true,
            'provider' => [
                'sort' => 'throughput', // https://openrouter.ai/docs/features/provider-routing
            ],
        ]);
    }

    private function processStreamResponse($response): void
    {
        $buffer = '';
        $body = $response->getBody();

        while (! $body->eof()) {
            if (connection_status() !== CONNECTION_NORMAL) {
                $this->isClientConnected = false;
                break;
            }

            // Read a chunk from the stream
            $chunk = $body->read(1024);
            $buffer .= $chunk;

            // Process complete lines
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                // Skip empty lines
                if (trim($line) === '') {
                    continue;
                }

                // Lines starting with "data: " contain the event data
                if (str_starts_with($line, 'data: ')) {
                    $dataLine = $this->processDataLine(
                        substr($line, 6), // Remove "data: " prefix
                    );

                    if (is_array($dataLine) && is_null($this->generationId)) {
                        $this->generationId = $dataLine['id'] ?? null;
                        $this->tokenCount = $dataLine['usage']['total_tokens'] ?? 0;
                    }
                }
            }
        }

        flush();
    }

    private function processDataLine(string $data)
    {
        // Check for the [DONE] message
        if ($data === '[DONE]') {
            $this->handleDoneMessage();
            return;
        }

        try {
            // Parse the JSON data
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (isset($json['choices'][0]['delta']['content'])) {
                $content = $json['choices'][0]['delta']['content'];
                $this->streamedMessage .= $content;

                echo 'data: '.json_encode(['content' => $content], JSON_THROW_ON_ERROR)."\n\n";

                flush();
            }

            return $json;

        } catch (Exception $e) {
            Log::error('Error processing SSE chunk: '.$e->getMessage());
        }
    }

    private function handleDoneMessage(): void
    {
        if ($this->isClientConnected) {
            $this->saveAssistantMessage();

            if (count($this->llmMessages) === 2) {
                $this->memory->updateConversationTitleFromMessages();
            }
        }

        echo "data: {\"content\":\"[DONE]\"}\n\n";
        flush();
    }

    private function handleCompletedResponse(): void
    {
        if (! $this->isClientConnected && $this->userMessageId) {
            // Remove the user message if the client has disconnected
            // We shouldn't save empty responses in the conversation
            $this->memory->removeMessage($this->userMessageId);
        }
    }

    private function saveAssistantMessage(): void
    {
        $endTime = microtime(true);

        $this->llmMessages[] = [
            'role' => 'assistant',
            'content' => $this->streamedMessage,
        ];

        $this->memory->addMessageViaPayload(
            new ChatOutputPayload(
                role: 'assistant',
                message: $this->streamedMessage,
                model: $this->llmModel['external_id'],
                generation_id: $this->generationId,
                token_count: $this->tokenCount,
                response_time_ms: (int) round(($endTime - $this->startTime) * 1000),
            )
        );
    }

    private function handleRequestException(RequestException $e): void
    {
        echo 'data: '.json_encode(['content' => 'Error: Could not connect to '.$this->llmModel['external_id'].' model. Please choose a different model'], JSON_THROW_ON_ERROR)."\n\n";
        echo "data: {\"content\":\"[DONE]\"}\n\n";

        if ($this->userMessageId) {
            $this->memory->removeMessage($this->userMessageId);
        }

        flush();
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
