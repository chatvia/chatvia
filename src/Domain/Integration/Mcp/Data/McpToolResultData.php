<?php

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Tool\Data\ToolDispatchResult;

/**
 * A `tools/call` result envelope (`{content, isError?, structuredContent?}`)
 * split into what the model reads and what the chat UI renders. McpClient
 * builds it from the server's reply, so the envelope is parsed in one place:
 *
 *   - `text`      → the `type: 'text'` parts of `content`, joined; a JSON dump
 *                   of the whole envelope when there are none.
 *   - artifacts   → well-known `structuredContent` shapes promoted to UI
 *                   blocks (an ECharts option, an inline form). Unknown keys
 *                   are ignored, so servers can ship side data safely.
 */
final readonly class McpToolResultData
{
    /**
     * @param  array<string, mixed>|null  $structuredContent
     */
    public function __construct(
        public string $text,
        public bool $isError,
        public ?array $structuredContent,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): self
    {
        $texts = [];
        foreach ((array) ($envelope['content'] ?? []) as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && isset($part['text'])) {
                $texts[] = (string) $part['text'];
            }
        }

        return new self(
            text: $texts === [] ? (string) json_encode($envelope) : implode("\n", $texts),
            isError: (bool) ($envelope['isError'] ?? false),
            structuredContent: is_array($envelope['structuredContent'] ?? null) ? $envelope['structuredContent'] : null,
        );
    }

    /**
     * As the tool layer's result: the text plus the renderable blocks, or a
     * recoverable error carrying the tool's own error text.
     */
    public function toDispatchResult(): ToolDispatchResult
    {
        return $this->isError
            ? ToolDispatchResult::error($this->text)
            : new ToolDispatchResult($this->text, $this->artifacts());
    }

    /**
     * The renderable blocks, each `['kind' => …, 'data' => …]` as the chat
     * renderer expects:
     *
     *   - `echarts_option` → an interactive chart (Charts tools).
     *   - `form`           → an inline FormKit form (RenderFormTool).
     *
     * @return list<array{kind: string, data: array<string, mixed>}>
     */
    public function artifacts(): array
    {
        $artifacts = [];

        foreach (['echarts_option', 'form'] as $kind) {
            if (is_array($this->structuredContent[$kind] ?? null)) {
                $artifacts[] = ['kind' => $kind, 'data' => $this->structuredContent[$kind]];
            }
        }

        return $artifacts;
    }
}
