<?php

namespace Domain\Chat\Services\Streaming\Sources;

use App\LLM\Contracts\LLMProviderInterface;
use Domain\Chat\Services\ChatContext;
use Domain\Chat\Services\ChatService;
use Domain\Chat\Services\Streaming\ErrorCode;
use Domain\Chat\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Chat\Services\Streaming\RequestDedupeStore;
use Domain\Chat\Services\Streaming\StreamEvent;
use Domain\Chat\Services\Streaming\StreamTransformerResolver;
use Domain\Chat\Services\Tooling\ToolDispatcherInterface;
use Domain\Chat\Services\Tooling\ToolDisplayResolver;
use Domain\Chat\Services\Tooling\ToolSelector;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmSource
{
    private const MAX_TOOL_ITERATIONS = 5;

    public function __construct(
        private readonly LLMProviderInterface $llmProvider,
        private readonly StreamTransformerResolver $transformers,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
        private readonly ToolSelector $toolSelector,
        private readonly ToolDispatcherInterface $dispatcher,
        private readonly ToolDisplayResolver $displayResolver,
    ) {}

    /**
     * Stream an LLM response using the v1 envelope.
     *
     * High-level flow:
     *   1. yield `start`
     *   2. tool loop:
     *      a. open upstream stream with `tools` (if any) and the current
     *         messages array
     *      b. relay `delta`, `meta` events from the transformer
     *      c. if the LLM emitted tool_calls, dispatch each via
     *         ToolDispatcherInterface, append a `role: 'tool'` message,
     *         then loop again
     *      d. if no tool calls (final text answer), break
     *   3. yield `sources`, persist, yield `done`
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $context->userMessageId,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);

        try {
            $agent = $context->requestData->agent;
            $toolDefinitions = $this->toolSelector->selectForTurn(
                $agent,
                $context->payload->message,
                $context->requestData->conversationId,
            );

            $iteration = 0;
            while ($iteration < self::MAX_TOOL_ITERATIONS) {
                $settings = $agent->llmSettings();
                if (! empty($toolDefinitions)) {
                    $settings['tools'] = $toolDefinitions;
                    $settings['tool_choice'] = 'auto';
                }

                $response = $this->llmProvider->stream(
                    $context->requestData->message->model->internal_name,
                    $llmMessages,
                    $settings,
                );

                $transformer = $this->transformers->for($this->llmProvider);
                yield from $transformer->transform($response, $context);

                $toolCalls = $context->takeToolCalls();
                if ($toolCalls === []) {
                    break;
                }

                // Persist the assistant turn that requested tools, then
                // execute each call and append a `role: 'tool'` message
                // with the result so the next iteration sees it.
                $llmMessages[] = [
                    'role' => 'assistant',
                    'content' => $context->streamedMessage,
                    'tool_calls' => array_map(static fn (array $tc): array => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => $tc['arguments'],
                        ],
                    ], $toolCalls),
                ];

                // Tool dispatch is synchronous + ordered with the LLM stream,
                // so the loading indicator is driven by SSE envelope events
                // here (not Reverb). Knowledge-base ingestion is async and
                // uses Reverb broadcasts — that distinction is intentional.
                foreach ($toolCalls as $tc) {
                    $display = $this->displayResolver->resolve($agent, $tc['name']);

                    yield StreamEvent::tool(
                        assistantMessageId: $assistantId,
                        phase: 'start',
                        toolCallId: $tc['id'],
                        name: $tc['name'],
                        args: $tc['arguments'],
                        kind: $display['kind'],
                        label: $display['label'],
                        iconUrl: $display['icon_url'],
                        brandColor: $display['brand_color'],
                    );

                    $dispatched = $this->dispatcher->dispatch($agent, $tc['name'], $tc['arguments']);

                    // Remember the tool name on the context so the
                    // StickyToolResolver can keep it available on
                    // follow-up turns of this conversation.
                    $context->toolCallNames[] = $tc['name'];

                    $llmMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc['id'],
                        'content' => $dispatched->text,
                    ];

                    // Stash artifacts on the context so handleChatCompletion can
                    // persist them onto the assistant message — otherwise charts
                    // only exist for the duration of the live SSE stream.
                    if ($dispatched->artifacts !== []) {
                        $context->artifacts = array_merge($context->artifacts, $dispatched->artifacts);
                    }

                    yield StreamEvent::tool(
                        assistantMessageId: $assistantId,
                        phase: 'end',
                        toolCallId: $tc['id'],
                        name: $tc['name'],
                        result: $dispatched->text,
                        artifacts: $dispatched->artifacts !== [] ? $dispatched->artifacts : null,
                    );
                }

                // Drop the visible text buffer between iterations so the
                // final saved assistant message is only the post-tool
                // answer, not the (usually empty) pre-tool fragment.
                $context->resetStreamedMessage();
                $iteration++;
            }

            if ($context->requestData->streamSources && $context->sources->isNotEmpty()) {
                yield StreamEvent::sources($assistantId, $context->sources->toArray());
            }

            $context->markEndTime();
            $this->chatService->handleChatCompletion($context);
            $this->rememberForReplay($context);

            yield StreamEvent::done(
                $assistantId,
                'complete',
                $context->getResponseTimeMs(),
            );
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);
            $this->rememberForReplay($context, partial: true);

            return;
        } catch (RequestException $e) {
            Log::error('Chat stream upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'The selected model could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Chat stream crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while generating the response.',
            );
            yield StreamEvent::done($assistantId, 'error');
        }
    }

    private function safeFinalize(ChatContext $context): void
    {
        try {
            $this->chatService->handleChatCompletion($context);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function rememberForReplay(ChatContext $context, bool $partial = false): void
    {
        $requestId = $context->requestData->requestId;
        if (! $requestId || $context->streamedMessage === '') {
            return;
        }

        try {
            $this->dedupe->remember(
                requestId: $requestId,
                assistantMessageId: $context->assistantMessageId,
                content: $context->streamedMessage,
                language: $context->queryLanguage,
                sources: $context->sources->toArray(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
