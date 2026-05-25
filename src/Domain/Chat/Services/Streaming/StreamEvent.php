<?php

namespace Domain\Chat\Services\Streaming;

use JsonException;

final readonly class StreamEvent
{
    public function __construct(
        public EventType $type,
        public string $id,
        public array $data = [],
        public ?int $seq = null,
    ) {}

    public static function start(string $assistantMessageId, array $data): self
    {
        return new self(EventType::Start, $assistantMessageId, $data);
    }

    public static function delta(string $assistantMessageId, string $text): self
    {
        return new self(EventType::Delta, $assistantMessageId, ['text' => $text]);
    }

    public static function meta(string $assistantMessageId, string $key, mixed $value): self
    {
        return new self(EventType::Meta, $assistantMessageId, ['key' => $key, 'value' => $value]);
    }

    public static function sources(string $assistantMessageId, array $sources): self
    {
        return new self(EventType::Sources, $assistantMessageId, ['sources' => $sources]);
    }

    public static function error(string $assistantMessageId, ErrorCode $code, string $message): self
    {
        return new self(EventType::Error, $assistantMessageId, [
            'code' => $code->value,
            'message' => $message,
            'retryable' => $code->isRetryable(),
        ]);
    }

    public static function done(string $assistantMessageId, string $reason, ?int $responseTimeMs = null): self
    {
        return new self(EventType::Done, $assistantMessageId, [
            'reason' => $reason,
            'response_time_ms' => $responseTimeMs,
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Tool-call lifecycle event. `phase` is one of:
     *   - 'start' — the LLM has emitted a complete tool call; about to dispatch
     *   - 'end'   — the dispatch finished (with `result` or `error`)
     *
     * The `start` phase optionally carries display metadata (`kind`,
     * `label`, `icon_url`, `brand_color`) so the chat UI can render a
     * branded icon + service name instead of a generic spinner.
     */
    public static function tool(
        string $assistantMessageId,
        string $phase,
        string $toolCallId,
        ?string $name = null,
        ?string $args = null,
        ?string $result = null,
        ?string $error = null,
        ?string $kind = null,
        ?string $label = null,
        ?string $iconUrl = null,
        ?string $brandColor = null,
    ): self {
        $data = [
            'phase' => $phase,
            'tool_call_id' => $toolCallId,
        ];
        if ($name !== null) {
            $data['name'] = $name;
        }
        if ($args !== null) {
            $data['args'] = $args;
        }
        if ($result !== null) {
            $data['result'] = $result;
        }
        if ($error !== null) {
            $data['error'] = $error;
        }
        if ($kind !== null) {
            $data['kind'] = $kind;
        }
        if ($label !== null) {
            $data['label'] = $label;
        }
        if ($iconUrl !== null) {
            $data['icon_url'] = $iconUrl;
        }
        if ($brandColor !== null) {
            $data['brand_color'] = $brandColor;
        }

        return new self(EventType::Tool, $assistantMessageId, $data);
    }

    public function withSeq(int $seq): self
    {
        return new self($this->type, $this->id, $this->data, $seq);
    }

    /**
     * @throws JsonException
     */
    public function toLine(): string
    {
        $payload = [
            'v' => 1,
            'seq' => $this->seq ?? 0,
            'id' => $this->id,
            'type' => $this->type->value,
            'data' => (object) $this->data,
        ];

        return 'data: '.json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )."\n\n";
    }
}
