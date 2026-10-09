<?php

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\StructuredReplyData;

/**
 * Sends a chat completion whose reply should be JSON and decodes it with
 * {@see LlmJson}. Use it with a `responseSchema` on the request when the
 * provider supports one, or with a prompt that asks for JSON when it does not.
 * The decoded value is null when the reply held no JSON; callers that need it
 * use {@see StructuredReplyData::requireData()}.
 */
final readonly class StructuredCompletion
{
    public function __construct(
        private LlmChat $chat,
    ) {}

    public function using(LlmProviderInterface $provider): self
    {
        return new self($this->chat->using($provider));
    }

    public function send(ChatRequestData $request): StructuredReplyData
    {
        $response = $this->chat->send($request);

        return new StructuredReplyData(
            data: LlmJson::decode($response->content),
            response: $response,
        );
    }
}
