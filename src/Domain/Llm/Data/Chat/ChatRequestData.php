<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Chat;

use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\ChatRole;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\LlmJson;
use Illuminate\Support\Str;
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
     * gets none, the reply length is no longer than the model writes, a
     * model that cannot be held to a JSON schema is asked for it in the
     * prompt, and a reasoning model thinks as little as it can (the app's own
     * tasks need no thinking).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'model' => $this->model->name,
            'messages' => $this->messagePayloads(),
        ];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        if ($this->responseSchema instanceof ResponseSchemaData && $this->model->takesResponseSchema()) {
            $payload['response_format'] = $this->responseSchema->toResponseFormat();
        }

        return $this->model->requestSettings($payload);
    }

    /**
     * The messages in wire format. For a model that cannot be held to the
     * reply's JSON schema, the schema is written into the system prompt; the
     * reply is read leniently either way ({@see LlmJson}).
     *
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function messagePayloads(): array
    {
        $messages = array_map(static fn (ChatMessageData $message): array => $message->toPayload(), $this->messages);

        if (! $this->responseSchema instanceof ResponseSchemaData || $this->model->takesResponseSchema()) {
            return $messages;
        }

        $instruction = $this->responseSchema->instruction();

        // Some chat templates take a single system message, and only first.
        if (($messages[0]['role'] ?? null) === ChatRole::System->value && filled($messages[0]['content'] ?? null) && ! is_array($messages[0]['content'])) {
            $messages[0]['content'] = Str::trim($messages[0]['content'])."\n\n".$instruction;

            return $messages;
        }

        return [ChatMessageData::system($instruction)->toPayload(), ...$messages];
    }
}
