<?php

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;

/**
 * Sends one non-streaming chat completion and returns a typed reply. Callers
 * build a {@see ChatRequestData} and read a {@see ChatResponseData}; only this
 * class touches the provider's wire format. It works with any
 * {@see LlmProviderInterface}, so the provider's own payload rules and token
 * usage tracking still apply.
 *
 * It sends to the configured chat provider; `using()` returns a copy bound to
 * another provider, such as the document-reading LLM.
 */
final readonly class LlmChat
{
    public function __construct(
        private LlmProviderInterface $provider,
    ) {}

    public function using(LlmProviderInterface $provider): self
    {
        return new self($provider);
    }

    public function send(ChatRequestData $request): ChatResponseData
    {
        $response = $this->provider->completion($request->toPayload())->throw();

        return ChatResponseData::fromResponse((array) $response->json());
    }
}
