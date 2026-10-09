<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Usage\TokenUsageContext;
use Generator;

/**
 * The default chat turn: streams the agent's configured model through the
 * shared tool loop. The envelope (start, errors, guardrails, persist, resume)
 * lives in {@see ToolLoopSource}.
 */
class LlmSource extends ToolLoopSource
{
    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
        AssistantReplyRecorder $replyRecorder,
        RequestDedupeStore $dedupe,
        ToolSelector $toolSelector,
        ToolLoopRunner $runner,
        GuardrailService $guardrails,
        GuardrailRecorder $guardrailRecorder,
        TokenUsageContext $tokenUsage,
    ) {
        parent::__construct($replyRecorder, $dedupe, $toolSelector, $runner, $guardrails, $guardrailRecorder, $tokenUsage);
    }

    /**
     * Stream an LLM response using the v1 envelope.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages): Generator
    {
        yield from $this->streamTurn($context, $llmMessages);
    }

    protected function provider(): LlmProviderInterface
    {
        return $this->llmProvider;
    }

    protected function modelName(ChatContext $context): string
    {
        return $context->requestData->message->model->internal_name;
    }

    protected function providerSlug(ChatContext $context): string
    {
        return $context->requestData->message->model->provider->value;
    }

    protected function logLabel(): string
    {
        return 'Chat';
    }

    protected function upstreamUnavailableMessage(): string
    {
        return __('The selected model could not be reached. Please try again.');
    }

    protected function turnFailedMessage(): string
    {
        return __('Something went wrong while generating the response.');
    }
}
