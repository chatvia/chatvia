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
 * It sends to the provider of the request's model; `using()` returns a copy
 * bound to one provider instead, such as the document-reading LLM.
 */
final readonly class LlmChat
{
    public function __construct(
        private LlmProviders $providers,
        private ?LlmProviderInterface $provider = null,
    ) {}

    public function using(LlmProviderInterface $provider): self
    {
        return new self($this->providers, $provider);
    }

    public function send(ChatRequestData $request): ChatResponseData
    {
        $provider = $this->provider ?? $this->providers->for($request->model);

        $response = $provider->completion($request->toPayload())->throw();

        return ChatResponseData::fromResponse((array) $response->json());
    }
}
