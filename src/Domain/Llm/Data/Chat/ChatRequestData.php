<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Chat;

use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Models\Llm;
use Spatie\LaravelData\Data;

/**
 * One non-streaming chat-completion request: the catalog model (whose
 * provider it goes to), the messages, what the call is for (the tag Spectra
 * shows on it; required, so no call goes out untagged) and the few sampling
 * options the app uses. Turned into the provider's wire format only at the
 * edge, by {@see self::toPayload()}.
 */
final class ChatRequestData extends Data
{
    /**
     * @param  list<ChatMessageData>  $messages
     */
    public function __construct(
        public Llm $model,
        public array $messages,
        public AiCallTag $tag,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?ResponseSchemaData $responseSchema = null,
    ) {}

    /**
     * The request body for an OpenAI-compatible `/chat/completions` endpoint.
     * Options that are not set are left out, so the provider default applies.
     * It is fitted to the model's facts: a model that takes no temperature
     * gets none, and a reasoning model thinks as little as it can (the app's
     * own tasks need no thinking).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'model' => $this->model->internal_name,
            'messages' => array_map(static fn (ChatMessageData $message): array => $message->toPayload(), $this->messages),
        ];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        if ($this->responseSchema instanceof ResponseSchemaData) {
            $payload['response_format'] = $this->responseSchema->toResponseFormat();
        }

        return $this->model->requestSettings($payload);
    }
}
