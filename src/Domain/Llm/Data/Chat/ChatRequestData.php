<?php

namespace Domain\Llm\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * One non-streaming chat-completion request: the model, the messages and the
 * few sampling options the app uses. Turned into the provider's wire format
 * only at the edge, by {@see self::toPayload()}.
 */
final class ChatRequestData extends Data
{
    /**
     * @param  list<ChatMessageData>  $messages
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?ResponseSchemaData $responseSchema = null,
    ) {}

    /**
     * The request body for an OpenAI-compatible `/chat/completions` endpoint.
     * Options that are not set are left out, so the provider default applies.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => array_map(static fn (ChatMessageData $message): array => $message->toPayload(), $this->messages),
        ];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        if ($this->responseSchema !== null) {
            $payload['response_format'] = $this->responseSchema->toResponseFormat();
        }

        return $payload;
    }
}
