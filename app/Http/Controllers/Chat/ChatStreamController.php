<?php

namespace App\Http\Controllers\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Data\Chat\LlmModelData;
use App\Models\Agent;
use App\Models\AgentFile;
use App\Support\Chat\ChatMemory;
use App\Support\KnowledgeBase;
use App\Support\Llms;
use App\Support\OpenRouter;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    private LlmModelData $llmModel;

    private ?string $userMessageId;

    private float $startTime;

    private ?string $generationId = null;

    private ?int $tokenCount = 0;

    private Collection $sources;

    private string $assistantMessageId = '';

    private $endTime;

    private bool $playground = false;

    public function __construct(
        private readonly ChatMemory $memory,
        private readonly KnowledgeBase $knowledgeBase) {}

    public function __invoke(Request $request, Llms $models, string $conversationId)
    {
        $request->validate([
            'model' => ['required', 'string', 'exists:llms,external_name'],
            'message' => ['required', 'string'],
            'updated_message_info' => ['nullable', 'array'],
            'playground' => ['nullable', 'bool'],
            'agent' => ['nullable', 'required_if:playground,true'],
        ]);

        if ($request->has('playground')) {
            $agent = Agent::find($request->input('agent'));
            $this->playground = true;
        } else {
            $agent = current_agent();
        }

        abort_if(
            boolean: ! $agent,
            code: 404
        );

        $this->agent = $agent;

        $this
            ->memory
            ->setConversationId($conversationId)
            ->setIsPlayground($this->playground)
            ->setAgent($agent);

        $this->payload = ChatInputPayload::from([
            'model' => $request->input('model'),
            'message' => $request->input('message'),
            'updated_message_info' => $request->input('updated_message_info', []),
            //            'message_for_llm' => $this->knowledgeBase->rewriteQuery($request->input('message')),
            'message_for_llm' => $request->input('message'),
        ]);

        $this->llmMessages = $this->memory->getLlmMessages();

        $this->llmModel = $models->getByExternalName($this->payload->model);

        $this->userMessageId = null;

        // Do not add a new message if the total number of messages is greater than 1
        // Because when we create a conversation, a new message will be added automatically.
        if (count($this->llmMessages) > 1) {
            $this->userMessageId = $this->memory->addMessageViaPayload($this->payload);
        }

        $this->assistantMessageId = Str::uuid()->toString();

        return $this->createStreamResponse();
    }

    private function createStreamResponse(): StreamedResponse
    {
        $this->startTime = microtime(true);

        return new StreamedResponse(function () {
            $this->setupStreamHeaders();

            // Set up client cancellation detection
            ignore_user_abort(false);

            try {
                $response = $this->makeOpenRouterRequest();
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
        $this->sources = collect();

        if ($agent->isKnowledgeable()) {
            $lastMessages = collect($this->memory->getLastMessages(6))->pluck('content')->toArray();

            $searchKnowledgeBase = true;

            if (count($lastMessages) > 1) {
                $searchKnowledgeBase = $this->knowledgeBase->shouldContinueSearching($lastMessages, $userMessageContent);
            }

            if ($searchKnowledgeBase) {
                $searchResults = $this->knowledgeBase->search($agent, $userMessageContent);

                // Enrich sources with file metadata
                $agentFileIds = collect($searchResults)->pluck('agent_file_id')->unique();
                $agentFiles = AgentFile::whereIn('id', $agentFileIds)
                    ->get()
                    ->keyBy('id');

                $this->sources = collect($searchResults)->map(function ($result) use ($agentFiles) {
                    $agentFile = $agentFiles->get($result->agent_file_id);
                    $metadata = json_decode($result->metadata, true);

                    return [
                        'content' => $result->chunk,
                        'metadata' => [
                            'filename' => $agentFile?->filename,
                            'document_id' => $result->id,
                            'agent_file_id' => $result->agent_file_id,
                        ],
                        'images' => $metadata['images'] ?? [],
                    ];
                });

                $userMessageContent = Str::of("### User Question:\n")
                    ->append($this->payload->message_for_llm)
                    ->append("### Retrieved Context\n")
                    ->append($this->sources->map(function ($chunk, $i) {
                        $text = "Chunk {$i}: ".$chunk['content']."\n";
                        if ($chunk['images']) {
                            $text .= "These are the images associated with  chunk {$i}:\n";
                            $text .= "You MUST Include one oe more of these images in markdown format if it does support the context and makes sense\n";
                            $i = 1;
                            foreach ($chunk['images'] as $image => $description) {
                                $text .= "Image {$i}: ".Storage::url($image)."\n";
                                $text .= "Description for image {$i}: {$description}\n";
                                $i++;
                            }
                        }
                        ray($text);

                        return $text;
                    })->implode("\n"))->toString();
            }
        }

        $userMessage = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    private function makeOpenRouterRequest(): ResponseInterface
    {
        $llmMessages = $this->prepareLlmMessages($this->llmMessages);

        $this->endTime = microtime(true);

        return app(OpenRouter::class)->stream($this->llmModel->internal_id, $llmMessages, $this->agent->llmSettings());
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

            $n = 0;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                // Skip empty lines
                if (trim($line) === '') {
                    continue;
                }

                // Lines starting with "data:" contain the event data
                if (str_starts_with($line, 'data: ')) {
                    $dataLine = $this->processDataLine(
                        $n,
                        substr($line, 6),
                    );

                    if (is_array($dataLine) && is_null($this->generationId)) {
                        $this->generationId = $dataLine['id'] ?? null;
                        $this->tokenCount = $dataLine['usage']['total_tokens'] ?? 0;
                    }
                }

                $n++;
            }
        }

        flush();
    }

    private function processDataLine(int $lineNumber, string $data)
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

                $messagePayload = ['content' => $content];

                if ($lineNumber === 0) {
                    $messagePayload['message_id'] = $this->assistantMessageId;
                }

                echo 'data: '.json_encode($messagePayload, JSON_THROW_ON_ERROR)."\n\n";

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
            if (! $this->playground) {
                $this->saveAssistantMessage();
            }

            if (! $this->playground && count($this->llmMessages) === 2) {
                $this->memory->updateConversationTitleFromMessages();
            }
        }

        $sources = $this->sources->toArray();

        echo 'sources: '.json_encode($sources, JSON_THROW_ON_ERROR)."\n\n";

        echo 'data: '.json_encode(['content' => '[DONE]'])."\n\n";

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
        $this->llmMessages[] = [
            'role' => 'assistant',
            'content' => $this->streamedMessage,
        ];

        $this->memory->addMessageViaPayload(
            new ChatOutputPayload(
                message_id: $this->assistantMessageId,
                role: 'assistant',
                message: $this->streamedMessage,
                model: $this->llmModel->id,
                generation_id: $this->generationId,
                token_count: $this->tokenCount,
                response_time_ms: (int) round(($this->endTime - $this->startTime) * 1000),
            )
        );

        $this->memory->storeSourcesForMessage($this->assistantMessageId, $this->sources->toArray());
    }

    private function handleRequestException(RequestException $e): void
    {
        echo 'data: '.json_encode(['content' => 'Error: Could not connect to '.$this->llmModel->id.' model. Please choose a different model'], JSON_THROW_ON_ERROR)."\n\n";
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
