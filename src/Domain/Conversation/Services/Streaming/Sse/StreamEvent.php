<?php

namespace Domain\Conversation\Services\Streaming\Sse;

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

    /**
     * Output guardrail correction: tell the client to replace the message it
     * already streamed with the redacted / safe `text`.
     */
    public static function replace(string $assistantMessageId, string $text): self
    {
        return new self(EventType::Replace, $assistantMessageId, ['text' => $text]);
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
     * Tool-call lifecycle event. `phase` ({@see ToolCallPhase}) is one of:
     *   - Start            — the LLM has emitted a complete tool call; about to dispatch
     *   - End              — the dispatch finished (with `result` or `error`)
     *   - AwaitingApproval — the call is gated by human-in-the-loop approval
     *                        and will not run until the user approves it
     *
     * The `start` / `awaiting_approval` phases optionally carry display
     * metadata (`kind`, `label`, `icon_url`, `brand_color`) so the chat UI can
     * render a branded icon + service name instead of a generic spinner.
     *
     * `awaiting_approval` additionally carries `approval_id` (the checkpoint the
     * client posts its decision against) and a human-readable `reason`.
     *
     * The `end` phase optionally carries `artifacts` — structured UI
     * payloads the renderer mounts inline in the assistant bubble (e.g. an
     * ECharts spec). The LLM does not see these; they're a side-channel
     * for the UI. Each artifact is `{kind: string, data: mixed}`.
     *
     * @param  array<int, array<string, mixed>>|null  $artifacts
     */
    public static function tool(
        string $assistantMessageId,
        ToolCallPhase $phase,
        string $toolCallId,
        ?string $name = null,
        ?string $args = null,
        ?string $result = null,
        ?string $error = null,
        ?string $kind = null,
        ?string $label = null,
        ?string $activityLabel = null,
        ?string $iconUrl = null,
        ?string $brandColor = null,
        ?string $approvalId = null,
        ?string $reason = null,
        ?array $artifacts = null,
        bool $internal = false,
    ): self {
        $data = [
            'phase' => $phase->value,
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
        if ($activityLabel !== null) {
            $data['activity_label'] = $activityLabel;
        }
        if ($iconUrl !== null) {
            $data['icon_url'] = $iconUrl;
        }
        if ($brandColor !== null) {
            $data['brand_color'] = $brandColor;
        }
        if ($approvalId !== null) {
            $data['approval_id'] = $approvalId;
        }
        if ($reason !== null) {
            $data['reason'] = $reason;
        }
        if ($artifacts !== null && $artifacts !== []) {
            $data['artifacts'] = $artifacts;
        }
        // First-party builtin capabilities (forms, charts, leads) are internal
        // plumbing — the client suppresses the "Calling …" activity indicator for
        // them. Artifacts (end phase) still render; the call is still logged
        // server-side.
        if ($internal) {
            $data['internal'] = true;
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
