<?php

namespace App\Http\Controllers\Chat;

use App\Models\Agent;
use App\Support\ChatMemory;
use App\Support\Models;
use App\Support\Rag;
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
    private bool $isClientConnected = true;

    private string $receivedMessage = '';

    public function __construct(private readonly Client $httpClient, private readonly ChatMemory $memory) {}

    public function __invoke(Request $request, Models $models, string $conversationId)
    {
        $payload = $request->json();
        $llmFormattedMessages = $this->memory->getLlmMessages($conversationId);

        $agent = \App\Support\Agent::resolveAgentFromSubdomain($request->subdomain());

        $modelExternalId = $payload->get('model');
        $modelId = $models->getModelByExternalId($modelExternalId, 'id');

        $userMessageId = null;

        if (count($llmFormattedMessages) > 1) {
            $userMessageId = $this->addUserMessageIfNeeded($agent, $conversationId, $payload);
        }

        return $this->createStreamResponse($userMessageId, $payload, $llmFormattedMessages, $conversationId, $modelId, $modelExternalId);
    }

    private function addUserMessageIfNeeded($agent, string $conversationId, $payload): ?string
    {
        return $this->memory->addUserMessage(
            conversationId: $conversationId,
            message: $payload->get('content'),
            llmMessage: app(Rag::class)->makeLlmMessageFromUserMessage($agent, $payload->get('content')),
            model: $payload->get('model'),
        );
    }

    private function createStreamResponse($userMessageId, $payload, array $messages, string $conversationId, string $modelId, string $modelExternalId): StreamedResponse
    {
        return new StreamedResponse(function () use ($userMessageId, $payload, $messages, $conversationId, $modelId, $modelExternalId) {
            $this->setupStreamHeaders();

            // Set up client cancellation detection
            ignore_user_abort(false);

            try {
                $llmMessages = $this->prepareLlmMessages($messages, $payload);
                $response = $this->makeOpenRouterRequest($modelId, $llmMessages);

                $this->processStreamResponse(
                    $response,
                    $messages,
                    $conversationId,
                    $modelExternalId
                );

                $this->handleCompletedResponse(
                    $conversationId,
                    $userMessageId
                );

            } catch (RequestException $e) {
                $this->handleRequestException($e, $modelExternalId, $conversationId, $userMessageId);
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

        //        echo ': ' . str_repeat(' ', 2048) . "\n\n";
        flush();

        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    private function prepareLlmMessages(array $messages, $payload): array
    {
        $agent = Agent::query()
            ->select('sub_domain', 'system_instructions')
            ->where('sub_domain', request()->subdomain())
            ->firstOrFail();

        $systemPrompt = [
            'role' => 'system',
            'content' => $agent->system_instructions,
        ];

        $content = $payload->get('content');

        if ($agent->type == 'knowledge') {
            $content = Str::of("### User Question:\n")
                ->append($content)
                ->append("### Retrieved Context\n")
                ->append(app(Rag::class)->chunks($agent->id, $content))
                ->toString();
        }

        $userMessage = [
            'role' => 'user',
            'content' => $content,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    private function makeOpenRouterRequest(string $modelId, array $llmMessages): ResponseInterface
    {
        return $this->httpClient->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $modelId,
                'messages' => $llmMessages,
                'stream' => true,
            ],
            'stream' => true,
            'provider' => [
                'sort' => 'throughput', // https://openrouter.ai/docs/features/provider-routing
            ],
        ]);
    }

    private function processStreamResponse($response, array &$messages, string $conversationId, string $modelExternalId): void
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
                    $this->processDataLine(
                        substr($line, 6), // Remove "data: " prefix
                        $messages,
                        $conversationId,
                        $modelExternalId
                    );
                }
            }
        }

        flush();
    }

    private function processDataLine(string $data, array &$messages, string $conversationId, string $modelExternalId): void
    {
        // Check for the [DONE] message
        if ($data === '[DONE]') {
            $this->handleDoneMessage($messages, $conversationId, $modelExternalId);

            return;
        }

        try {
            // Parse the JSON data
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (isset($json['choices'][0]['delta']['content'])) {
                $content = $json['choices'][0]['delta']['content'];
                $this->receivedMessage .= $content;
                echo 'data: '.json_encode(['content' => $content], JSON_THROW_ON_ERROR)."\n\n";
                flush();
            }
        } catch (Exception $e) {
            Log::error('Error processing SSE chunk: '.$e->getMessage());
        }
    }

    private function handleDoneMessage(array &$messages, string $conversationId, string $modelExternalId): void
    {
        if ($this->isClientConnected) {
            $this->saveAssistantMessage($messages, $conversationId, $modelExternalId);

            if (count($messages) === 2) {
                $this->memory->updateConversationTitleFromMessages($conversationId, $messages);
            }
        }

        echo "data: {\"content\":\"[DONE]\"}\n\n";
        flush();
    }

    private function handleCompletedResponse(string $conversationId, ?string $userMessageId): void
    {
        if (! $this->isClientConnected && $userMessageId) {
            $this->memory->removeMessage($conversationId, $userMessageId);
        }
    }

    private function saveAssistantMessage(array &$messages, string $conversationId, string $modelExternalId): void
    {
        $messages[] = [
            'role' => 'assistant',
            'content' => $this->receivedMessage,
        ];

        $this->memory->addMessage(
            conversationId: $conversationId,
            role: 'assistant',
            message: $this->receivedMessage,
            model: $modelExternalId
        );
    }

    private function handleRequestException(RequestException $e, string $modelExternalId, string $conversationId, ?string $userMessageId): void
    {
        echo 'data: '.json_encode(['content' => 'Error: Could not connect to '.$modelExternalId.' model. Please choose a different model'], JSON_THROW_ON_ERROR)."\n\n";
        echo "data: {\"content\":\"[DONE]\"}\n\n";

        if ($userMessageId) {
            $this->memory->removeMessage($conversationId, $userMessageId);
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
