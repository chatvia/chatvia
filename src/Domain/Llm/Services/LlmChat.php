<?php

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;

/**
 * Sends one non-streaming chat completion and returns a typed reply. Callers
 * build a {@see ChatRequestData} and read a {@see ChatResponseData}; only this
 * class touches the provider's wire format. It works with any
 * {@see LlmProviderInterface}, so the provider's own payload rules and token
 * usage tracking still apply. The call carries the request's tag in
 * Spectra ({@see AiCallTags}).
 *
 * It sends to the provider of the request's model; `using()` returns a copy
 * bound to one provider instead, such as the document-reading LLM.
 * `sendAsync()` sends without waiting, so several calls of one request can
 * run at the same time.
 */
final readonly class LlmChat
{
    public function __construct(
        private LlmProviders $providers,
        private AiCallTags $tags,
        private ?LlmProviderInterface $provider = null,
    ) {}

    public function using(LlmProviderInterface $provider): self
    {
        return new self($this->providers, $this->tags, $provider);
    }

    public function send(ChatRequestData $request): ChatResponseData
    {
        $provider = $this->provider ?? $this->providers->for($request->model);

        $response = $this->tags->during($request->tag, fn () => $provider->completion($request->toPayload())->throw());

        return ChatResponseData::fromResponse((array) $response->json());
    }

    /**
     * Send the request now and return the reply later: the promise resolves
     * to the reply, or is rejected where send() would throw.
     *
     * @return PromiseInterface resolving to ChatResponseData
     */
    public function sendAsync(ChatRequestData $request): PromiseInterface
    {
        $provider = $this->provider ?? $this->providers->for($request->model);

        return $this->tags->during($request->tag, fn () => $provider->completionAsync($request->toPayload()))
            ->then(static fn (Response $response): ChatResponseData => ChatResponseData::fromResponse((array) $response->json()));
    }
}
